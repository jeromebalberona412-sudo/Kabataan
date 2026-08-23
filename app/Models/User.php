<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_KABATAAN = 'kabataan';

    public const ROLE_USER = 'user';

    public const ROLE_SK_FED = 'sk_fed';

    public const ROLE_SK_OFFICIAL = 'sk_official';

    public const ROLE_ADMIN = 'admin';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_INACTIVE = 'INACTIVE';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'tenant_id',
        'barangay_id',
        'role',
        'status',
        'pending_email',
        'email_change_token',
        'email_change_token_expires_at',
        'email_change_verified_at',
        'email_change_last_sent_at',
        'pending_password',
        'password_change_token',
        'password_change_token_expires_at',
        'password_change_last_sent_at',
        'must_change_password',
        'profile_image_url',
        'profile_image_public_id',
        'profile_image_uploaded_at',
        'profile_image_change_available_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_change_token_expires_at' => 'datetime',
            'email_change_verified_at' => 'datetime',
            'email_change_last_sent_at' => 'datetime',
            'password_change_token_expires_at' => 'datetime',
            'password_change_last_sent_at' => 'datetime',
            'must_change_password' => 'boolean',
            'profile_image_uploaded_at' => 'datetime',
            'profile_image_change_available_at' => 'datetime',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        $userRole = strtolower(trim((string) $this->role));

        foreach ($roles as $role) {
            if ($userRole === strtolower(trim($role))) {
                return true;
            }
        }

        return false;
    }

    public function isKabataanAccount(): bool
    {
        $allowedRoles = config('kabataan_auth.allowed_roles', [self::ROLE_KABATAAN, self::ROLE_USER]);

        if (! is_array($allowedRoles) || $allowedRoles === []) {
            return false;
        }

        return $this->hasRole(...$allowedRoles);
    }
}
