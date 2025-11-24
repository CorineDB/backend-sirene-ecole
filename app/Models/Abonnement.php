<?php

namespace App\Models;

use App\Enums\StatutAbonnement;
use App\Enums\StatutSirene;
use App\Traits\HasUlid;
use App\Traits\HasQrCodeAbonnement;
use App\Traits\HasTokenCrypte;
use App\Traits\HasNumeroAbonnement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model
{
    use HasUlid, SoftDeletes, HasQrCodeAbonnement, HasTokenCrypte, HasNumeroAbonnement;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userAccess', function (Builder $builder) {
            $user = auth()->user();

            if (!$user) {
                return;
            }

            // Si l'utilisateur est une école, filtrer par école
            if ($user->isEcoleUser()) {
                $ecole = $user->userAccount;
                if ($ecole) {
                    $builder->where('ecole_id', $ecole->id);
                }
            }

            // Si admin ou technicien, pas de filtre (retourne tous les abonnements)
        });

        // ========== HOOKS DE VALIDATION MÉTIER ==========

        /**
         * Hook avant création : valider les règles métier
         */
        static::creating(function (Abonnement $abonnement) {
            // Vérifier que la sirène est spécifiée
            if (empty($abonnement->sirene_id)) {
                throw new \Exception('La sirène est requise pour créer un abonnement.');
            }

            // Vérifier qu'il n'y a pas déjà un abonnement actif/en attente/suspendu pour cette sirène
            // Exception : si c'est un renouvellement (parent_abonnement_id existe)
            $hasActiveSubscription = self::where('sirene_id', $abonnement->sirene_id)
                ->whereIn('statut', [
                    StatutAbonnement::ACTIF,
                    StatutAbonnement::EN_ATTENTE,
                    StatutAbonnement::SUSPENDU
                ])
                ->exists();

            if ($hasActiveSubscription) {
                throw new \Exception(
                    'Cette sirène a déjà un abonnement actif, en attente ou suspendu. ' .
                    'Veuillez d\'abord annuler ou laisser expirer l\'abonnement existant.'
                );
            }

            // Si c'est un renouvellement, vérifier que le parent existe et est EXPIRE ou ANNULE
            if (!empty($abonnement->parent_abonnement_id)) {
                $parentAbonnement = self::withoutGlobalScope('userAccess')
                    ->find($abonnement->parent_abonnement_id);

                if (!$parentAbonnement) {
                    throw new \Exception('L\'abonnement parent n\'existe pas.');
                }

                if (!$parentAbonnement->canBeRenewed()) {
                    throw new \Exception(
                        'L\'abonnement parent ne peut pas être renouvelé. ' .
                        'Les abonnements actifs, suspendus ou en attente (sans parent) ne peuvent pas être renouvelés.'
                    );
                }
            }
        });

        /**
         * Hook avant modification : valider les changements de statut
         */
        static::updating(function (Abonnement $abonnement) {
            // Si on change le statut
            if ($abonnement->isDirty('statut')) {
                $oldStatut = $abonnement->getOriginal('statut');
                $newStatut = $abonnement->statut;

                // Si on passe à ACTIF, vérifier qu'un paiement validé existe
                if ($newStatut === StatutAbonnement::ACTIF && $oldStatut !== StatutAbonnement::ACTIF->value) {
                    // Charger les paiements si pas déjà chargés
                    if (!$abonnement->relationLoaded('paiements')) {
                        $abonnement->load('paiements');
                    }

                    $hasPaiementValide = $abonnement->paiements()
                        ->where('statut', 'valide')
                        ->exists();

                    if (!$hasPaiementValide) {
                        throw new \Exception(
                            'Impossible d\'activer un abonnement sans paiement validé.'
                        );
                    }
                }

                // Si on passe à SUSPENDU, vérifier que l'abonnement est ACTIF
                if ($newStatut === StatutAbonnement::SUSPENDU && $oldStatut !== StatutAbonnement::ACTIF->value) {
                    throw new \Exception(
                        'Seuls les abonnements actifs peuvent être suspendus.'
                    );
                }

                // Si on passe à ANNULE, vérifier que l'abonnement n'est pas déjà EXPIRE ou ANNULE
                if ($newStatut === StatutAbonnement::ANNULE) {
                    if (in_array($oldStatut, [StatutAbonnement::EXPIRE->value, StatutAbonnement::ANNULE->value])) {
                        throw new \Exception(
                            'Les abonnements déjà expirés ou annulés ne peuvent pas être annulés.'
                        );
                    }
                }
            }

            // Si on change la sirène, vérifier qu'il n'y a pas déjà un abonnement actif pour la nouvelle sirène
            if ($abonnement->isDirty('sirene_id')) {
                $hasActiveSubscription = self::where('sirene_id', $abonnement->sirene_id)
                    ->where('id', '!=', $abonnement->id)
                    ->whereIn('statut', [
                        StatutAbonnement::ACTIF,
                        StatutAbonnement::EN_ATTENTE,
                        StatutAbonnement::SUSPENDU
                    ])
                    ->exists();

                if ($hasActiveSubscription) {
                    throw new \Exception(
                        'La nouvelle sirène a déjà un abonnement actif, en attente ou suspendu.'
                    );
                }
            }
        });
    }

    protected $primaryKey = 'id';
    protected $keyType = 'string';

    protected $table = 'abonnements';

    protected $fillable = [
        'ecole_id',
        'site_id',
        'sirene_id',
        'parent_abonnement_id',
        'numero_abonnement',
        'date_debut',
        'date_fin',
        'montant',
        'statut',
        'auto_renouvellement',
        'notes',
        'qr_code_path',
    ];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin' => 'date:Y-m-d',
        'montant' => 'decimal:2',
        'statut' => StatutAbonnement::class,
        'auto_renouvellement' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Attributs à ajouter automatiquement dans les réponses JSON
     */
    protected $appends = ['qr_code_url'];

    // Relations
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function sirene(): BelongsTo
    {
        return $this->belongsTo(Sirene::class, 'sirene_id');
    }

    public function parentAbonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'parent_abonnement_id');
    }

    public function childAbonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'parent_abonnement_id');
    }

    public function token(): HasOne
    {
        return $this->hasOne(TokenSirene::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    // Accessors
    /**
     * Obtenir l'URL publique du QR code
     * Utilise la méthode getQrCodeUrl() définie dans le trait HasQrCodeAbonnement
     */
    public function getQrCodeUrlAttribute(): ?string
    {
        return $this->getQrCodeUrl();
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->statut === StatutAbonnement::ACTIF
            && $this->date_fin >= now();
    }

    public function isExpired(): bool
    {
        return $this->date_fin < now();
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->date_fin, false));
    }

    // ========== RÈGLES MÉTIER ==========

    /**
     * Vérifie si l'abonnement peut être annulé
     */
    public function canBeCancelled(): bool
    {
        // Ne peut pas annuler un abonnement déjà expiré ou annulé
        return !in_array($this->statut, [StatutAbonnement::EXPIRE, StatutAbonnement::ANNULE]);
    }

    /**
     * Vérifie si l'abonnement peut être suspendu
     */
    public function canBeSuspended(): bool
    {
        // Seuls les abonnements actifs peuvent être suspendus
        return $this->statut === StatutAbonnement::ACTIF;
    }

    /**
     * Vérifie si l'abonnement peut être réactivé
     */
    public function canBeReactivated(): bool
    {
        // Seuls les abonnements suspendus peuvent être réactivés
        return $this->statut === StatutAbonnement::SUSPENDU
            && $this->date_fin >= now(); // Et non expirés
    }

    /**
     * Vérifie si l'abonnement peut être renouvelé
     */
    public function canBeRenewed(): bool
    {
        // Ne peut pas renouveler :
        // - Un abonnement actif
        // - Un abonnement suspendu
        // - Un abonnement en attente sans parent (création initiale)

        if ($this->statut === StatutAbonnement::ACTIF) {
            return false;
        }

        if ($this->statut === StatutAbonnement::SUSPENDU) {
            return false;
        }

        if ($this->statut === StatutAbonnement::EN_ATTENTE && !$this->parent_abonnement_id) {
            return false;
        }

        return true; // EXPIRE ou ANNULE peuvent être renouvelés
    }

    /**
     * Vérifie si une sirène a déjà un abonnement actif, en attente ou suspendu
     */
    public static function sireneHasActiveOrPendingSubscription(string $sireneId): bool
    {
        return self::where('sirene_id', $sireneId)
            ->whereIn('statut', [
                StatutAbonnement::ACTIF,
                StatutAbonnement::EN_ATTENTE,
                StatutAbonnement::SUSPENDU
            ])
            ->exists();
    }

    /**
     * Vérifie si l'abonnement a un paiement validé
     */
    public function hasPaiementValide(): bool
    {
        return $this->paiements()
            ->where('statut', 'valide')
            ->exists();
    }

    /**
     * Récupère le token actif de l'abonnement
     */
    public function tokenActif()
    {
        return $this->token()->where('actif', true);
    }

    // ========== GESTION AUTOMATIQUE ==========

    /**
     * Expire tous les tokens de l'abonnement
     */
    public function expirerTousLesTokens(): void
    {
        $this->token()->update([
            'actif' => false,
            'date_expiration' => now()
        ]);
    }

    /**
     * Met à jour le statut de la sirène en fonction du statut de l'abonnement
     */
    public function updateSireneStatus(): void
    {
        if (!$this->sirene_id) {
            return;
        }

        $sirene = $this->sirene;
        if (!$sirene) {
            return;
        }

        // Sauvegarder l'ancien statut
        $oldStatut = $sirene->statut;

        // Déterminer le nouveau statut
        $newStatut = match($this->statut) {
            StatutAbonnement::ACTIF => StatutSirene::RESERVE,
            StatutAbonnement::EN_ATTENTE => StatutSirene::RESERVE,
            StatutAbonnement::SUSPENDU => $oldStatut, // Ne change pas le statut si suspendu
            StatutAbonnement::EXPIRE, StatutAbonnement::ANNULE =>
                // Si expire/annule, remet en stock
                // Exception : garde EN_PANNE (mais INSTALLE peut être changé vers EN_STOCK)
                $oldStatut === StatutSirene::EN_PANNE
                    ? $oldStatut
                    : StatutSirene::EN_STOCK,
        };

        // Mettre à jour uniquement si le statut change
        if ($newStatut !== $oldStatut) {
            $sirene->old_statut = $oldStatut;
            $sirene->statut = $newStatut;
            $sirene->save();
        }
    }

    /**
     * Gère l'activation de l'abonnement (token + sirène)
     */
    public function activate(): void
    {
        // Mettre à jour le statut de la sirène
        $this->updateSireneStatus();

        // Générer le token si l'abonnement est actif
        if ($this->statut === StatutAbonnement::ACTIF) {
            $this->regenererToken();
        }
    }

    /**
     * Gère la suspension de l'abonnement (token + sirène)
     */
    public function suspend(): void
    {
        // Expirer les tokens
        $this->expirerTousLesTokens();

        // Le statut de la sirène reste inchangé lors d'une suspension
        // (elle reste RESERVE ou INSTALLE selon son état actuel)
    }

    /**
     * Gère l'annulation de l'abonnement (token + sirène)
     */
    public function cancel(): void
    {
        // Expirer les tokens
        $this->expirerTousLesTokens();

        // Mettre à jour le statut de la sirène
        $this->updateSireneStatus();
    }
}
