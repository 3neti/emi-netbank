<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Tests\Models;

use Bavix\Wallet\Traits\CanConfirm;
use Bavix\Wallet\Traits\HasWalletFloat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LBHurtado\Merchant\Models\Merchant;
use LBHurtado\Merchant\Traits\HasMerchant;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\PaymentGateway\Database\Factories\AnotherUserFactory;

/**
 * AnotherClass User.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Merchant $merchant
 *
 * @method int getKey()
 */
class AnotherUser extends Authenticatable
{
    use CanConfirm;
    use HasChannels;
    use HasFactory;
    use HasMerchant;
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

    public static function newFactory(): AnotherUserFactory
    {
        return AnotherUserFactory::new();
    }
}
