<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WindowsKey extends Model
{
    public const string TYPE_PRO = 'Pro';
    public const string TYPE_HOME = 'Home';
    public const string KEY_TYPE_USED = 'Used';
    public const string KEY_TYPE_NOT_USED = 'Not used';
    public const string KEY_TYPE_RMA_NEEDED = 'RMA Needed';
    public const string KEY_TYPE_SENT_TO_MANUFACTURER = 'Sent to manufacturer';
    public const string KEY_TYPE_DOWNLOADED = 'Downloaded';
    public const string KEY_TYPE_TRANSFERRED = 'Transferred';
    public const string KEY_TYPE_REFUND = 'Refund';
    public const string PRODUCT_PATTERN = 'WIN-%';

    protected $table = 'tbl_windows_activation_keys';

    protected $fillable = ['key', 'key_type', 'vendor', 'hashed_key', 'status', 'order_id', 'rma_error', 'serial_key'];

    public function logs()
    {
        return $this->hasMany(Log::class, 'entity_id');
    }
}
