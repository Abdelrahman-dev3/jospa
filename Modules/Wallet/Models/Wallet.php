<?php

namespace Modules\Wallet\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Wallet\Database\factories\WalletFactory;

class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */

    protected $table = 'wallet';
    protected $fillable = [
        'title',
        'user_id',
        'amount',
        'status'
    ];
    protected $cast = [
        'amount' => 'double' 
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
   
}
