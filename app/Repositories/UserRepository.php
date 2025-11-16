<?php

namespace App\Repositories;

use App\Enums\TypeUtilisateur;
use App\Models\User;
use App\Repositories\Contracts\OtpCodeRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserInfoRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Support\Str;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\SmsService;
use Carbon\Carbon;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected UserInfoRepositoryInterface $userInfoRepository;
    protected OtpCodeRepositoryInterface $otpCodeRepository;
    protected RoleRepositoryInterface $roleRepository;
    protected SmsService $smsService;

    public function __construct(User $model, UserInfoRepositoryInterface $userInfoRepository, OtpCodeRepositoryInterface $otpCodeRepository, RoleRepositoryInterface $roleRepository, SmsService $smsService)
    {
        parent::__construct($model);
        $this->userInfoRepository = $userInfoRepository;
        $this->otpCodeRepository = $otpCodeRepository;
        $this->roleRepository = $roleRepository;
        $this->smsService = $smsService;
    }

    public function create(array $data): Model
    {
        try {
            DB::beginTransaction();

            $plainPassword = null;
            if (isset($data['mot_de_passe'])) {
                $plainPassword = $data['mot_de_passe']; // Capture plain-text password
                $data['mot_de_passe'] = Hash::make($plainPassword);
            } else {
                $plainPassword = Str::random(12); // Generate a 12-character temporary password
                $data['mot_de_passe'] = Hash::make($plainPassword);
            }

            if (isset($data['role_id'])) {
                if (!$this->roleRepository->find($data['role_id'])) {
                    throw new Exception('Role with ID ' . $data['role_id'] . ' not found.');
                }
            }

            // Inherit user_account_type from authenticated user if not provided
            if (auth()->check() && auth()->user()->userAccount) {
                $data['user_account_type_id'] = auth()->user()->userAccount->id;
                $data['user_account_type_type'] = get_class(auth()->user()->userAccount);
            } /* else {

                // Ensure user_account_type_id and user_account_type_type default to null if not provided
                $data['user_account_type_id'] = null;
                $data['user_account_type_type'] =  null;
            } */

            $userInfoData = $data['userInfoData'] ?? [];

            unset($data['userInfoData']);

            // Generate identifiant if not provided
            if (!isset($data['identifiant'])) {
                if (isset($userInfoData['telephone'])) {
                    $data['identifiant'] = $userInfoData['telephone'];
                } elseif (isset($userInfoData['email'])) {
                    $data['identifiant'] = $userInfoData['email'];
                } else {
                    $data['identifiant'] = (string) Str::uuid(); // Fallback to UUID
                }
            }

            // Generate nom_utilisateur if not provided
            if (!isset($data['nom_utilisateur'])) {
                if (isset($data['user_account_type_type']) && $data['user_account_type_type'] === \App\Models\Ecole::class) {
                    // For Ecole users, try to use a school name from userInfoData
                    if (isset($userInfoData['nom_etablissement'])) {
                        $data['nom_utilisateur'] = Str::slug($userInfoData['nom_etablissement']);
                    } else {
                        $data['nom_utilisateur'] = $data['identifiant'];
                    }
                } elseif (isset($userInfoData['prenom']) && isset($userInfoData['nom'])) {
                    // For individual users, use first and last name
                    $data['nom_utilisateur'] = Str::slug($userInfoData['prenom'] . ' ' . $userInfoData['nom']);
                } elseif ( isset($userInfoData['nom'])) {
                    // For individual users, use first and last name
                    $data['nom_utilisateur'] = Str::slug($userInfoData['nom']);
                } else {
                    // Fallback to identifiant
                    $data['nom_utilisateur'] = $data['identifiant'];
                }
            }

            $user = parent::create($data);

            if ($user && !empty($userInfoData)) {
                $userInfoData['user_id'] = $user->id;
                $this->userInfoRepository->create($userInfoData);
            }

            // Generate and store OTP for account activation
            $otpCode = null;
            if ($user && isset($userInfoData['telephone'])) {
                $otpCode = rand(100000, 999999); // Generate a 6-digit OTP
                $this->otpCodeRepository->create([
                    'user_id' => $user->id,
                    'telephone' => $userInfoData['telephone'],
                    'code' => (string) $otpCode,
                    'expire_le' => now()->addMinutes(10), // OTP valid for 10 minutes

                    //'type' => "activation",
                    'verifie' => false,
                    'date_expiration' => Carbon::now()->addMinutes((int) intval(config('services.otp.expiration_minutes', 5))),
                    'tentatives' => 0,
                ]);
            }

            DB::commit();

            // Envoyer SMS de confirmation de création de compte (SMS 1)
            // L'OTP sera envoyé lors de la première demande de connexion
            if ($user && isset($userInfoData['telephone'])) {
                $message = "Votre compte a été créé. Identifiant: {$user->identifiant}. Mot de passe temporaire: {$plainPassword}. Un code OTP vous sera envoyé lors de votre première connexion.";
                try {
                    $this->smsService->sendSms($userInfoData['telephone'], $message);
                    Log::info("Account creation SMS sent to user {$user->id} {$plainPassword}");
                } catch (\Exception $e) {
                    Log::error("Failed to send account creation SMS for user {$user->id}: " . $e->getMessage());
                }
            } else {
                Log::warning("Could not send SMS for user {$user->id}. Missing phone.");
            }

            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            if (isset($data['role_id'])) {
                if (!$this->roleRepository->find($data['role_id'])) {
                    throw new Exception('Role with ID ' . $data['role_id'] . ' not found.');
                }
            }

            $userInfoData = $data['userInfoData'] ?? [];
            unset($data['userInfoData']);

            $user = parent::update($id, $data);

            if ($user && !empty($userInfoData)) {
                $existingUserInfo = $this->userInfoRepository->findByUserId($id);
                if ($existingUserInfo) {
                    $this->userInfoRepository->update($existingUserInfo->id, $userInfoData);
                } else {
                    $userInfoData['user_id'] = $id;
                    $this->userInfoRepository->create($userInfoData);
                }
            }

            DB::commit();
            return $user; // Return the updated user with fresh data
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::update - " . $e->getMessage());
            throw $e;
        }
    }

    public function findByIdentifier(string $identifier): ?User
    {
        try {
            return $this->model->where('identifiant', $identifier)->first();
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::findByIdentifier - " . $e->getMessage());
            throw $e;
        }
    }

    public function findByEmail(string $email): ?User
    {
        try {
            return $this->model->whereHas('userInfo', function ($query) use ($email) {
                $query->where('email', $email);
            })->first();
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::findByEmail - " . $e->getMessage());
            throw $e;
        }
    }

    public function findByPhone(string $phone): ?User
    {
        try {
            return $this->model->whereHas('userInfo', function ($query) use ($phone) {
                $query->where('telephone', $phone);
            })->first();
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::findByPhone - " . $e->getMessage());
            throw $e;
        }
    }

    public function findByUserAccount(string $accountType, string $accountId): ?User
    {
        try {
            return $this->model
                ->where('user_account_type_type', $accountType)
                ->where('user_account_type_id', $accountId)
                ->first();
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::findByUserAccount - " . $e->getMessage());
            throw $e;
        }
    }

    public function userExists(string $identifier): bool
    {
        try {
            return $this->model->where('identifiant', $identifier)->exists();
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::userExists - " . $e->getMessage());
            throw $e;
        }
    }

    public function updateStatus(string $id, int $status): ?User
    {
        try {
            $user = $this->find($id);
            if ($user) {
                $user->statut = $status;
                $user->save();
            }
            return $user;
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::updateStatus - " . $e->getMessage());
            throw $e;
        }
    }

    public function activateUser(string $id): ?User
    {
        try {
            $user = $this->find($id);
            if ($user) {
                $user->actif = true;
                $user->save();
            }
            return $user;
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::activateUser - " . $e->getMessage());
            throw $e;
        }
    }

    public function deactivateUser(string $id): ?User
    {
        try {
            $user = $this->find($id);
            if ($user) {
                $user->actif = false;
                $user->save();
            }
            return $user;
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::deactivateUser - " . $e->getMessage());
            throw $e;
        }
    }

    public function updatePassword(string $id, string $newPassword): ?User
    {
        try {
            $user = $this->find($id);
            if ($user) {
                $user->mot_de_passe = Hash::make($newPassword);
                $user->save();
            }
            return $user;
        } catch (Throwable $e) {
            Log::error("Error in " . get_class($this) . "::updatePassword - " . $e->getMessage());
            throw $e;
        }
    }


    public function delete(string $id): bool
    {
        try {
            DB::beginTransaction();

            $user = parent::find($id);
            if (!$user) {
                return false;
            }

            // Delete associated UserInfo
            $userInfo = $user->userInfo;
            if ($userInfo) {
                $this->userInfoRepository->delete($userInfo->id);
            }

            // Delete the User
            $deleteResult = parent::delete($id);

            if (!$deleteResult) {
                DB::rollBack();
                return false;
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::delete - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Paginate roles with filters
     *
     * @param int $perPage
     * @param array $columns
     * @param array $relations
     * @param string|null $profilableType
     * @param string|null $profilableId
     * @param bool $excludeDefaults
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate(
        int $perPage = 15,
        array $columns = ['*'],
        array $relations = []
    ): \Illuminate\Pagination\LengthAwarePaginator {
        $query = $this->model->with($relations);

        $profilableType = auth()->user()->user_account_type_type;

        // Exclure les rôles par défaut (rôles système sans profilable)
        if (!$profilableType) {
            $query->whereNull('user_account_type_id')
                  ->whereNull('user_account_type_type')
                  ->whereNotIn("type", [TypeUtilisateur::ADMIN->value, TypeUtilisateur::ECOLE->value, TypeUtilisateur::TECHNICIEN->value]);
        }

        // Filtrer par profilable_type si fourni
        if ($profilableType !== null) {
            $query->where('user_account_type_type', $profilableType);
        }
        $profilableId = auth()->user()->user_account_type_id;

        // Filtrer par profilable_id si fourni
        if ($profilableId !== null) {
            $query->where('user_account_type_id', $profilableId);
        }

        return $query->paginate($perPage, $columns);
    }

}
