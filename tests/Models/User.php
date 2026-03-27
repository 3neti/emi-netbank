<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Tests\Models;

use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanConfirm;
use Bavix\Wallet\Traits\HasWalletFloat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LBHurtado\Merchant\Contracts\MerchantInterface;
use LBHurtado\Merchant\Models\Merchant;
use LBHurtado\Merchant\Traits\HasMerchant;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\PaymentGateway\Database\Factories\UserFactory;
use LBHurtado\Wallet\Services\WalletProvisioningService;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use LBHurtado\PaymentGateway\Traits\HasTopUps;

/**
 * Class User.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Merchant $merchant
 *
 * @method int getKey()
 */
class User extends Authenticatable implements Confirmable, MerchantInterface, Wallet
{
    use CanConfirm;
    use HasChannels;
    use HasFactory;
    use HasMerchant;
    use HasPlatformWallets;
    use HasTopUps;
    use HasWalletFloat;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public static function booted(): void
    {
        static::created(function (User $user) {
            $walletService = app(WalletProvisioningService::class);
            $walletService->createDefaultWalletsForUser($user);
        });
    }

    public function topUps()
    {
        return $this->hasMany(TopUp::class);
    }
}
