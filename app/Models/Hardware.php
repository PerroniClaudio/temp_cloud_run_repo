<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hardware extends Model {
  use SoftDeletes, HasFactory;

  // Specifica il nome della tabella
  protected $table = 'hardware';

  protected $fillable = [
    'make',
    'model',
    'serial_number',
    'company_asset_number',
    'support_label',
    'purchase_date',
    'company_id',
    'hardware_type_id',
    'ownership_type',
    'ownership_type_note',
    'notes',
    'is_exclusive_use',
  ];

  protected static function boot() {
    parent::boot();
    // è stato deciso di tenere i log delle assegnazioni, nel caso dell'azienda si deve intercettare il company_id nell'hardware.
    
    static::creating(function ($model) {
      // Esegue controlli prima di salvare il modello.
      // Deve esserci obbligatoriamente o il cespite aziendale o l'identificativo.
      if (!$model->company_asset_number && !$model->support_label) {
        throw new \Exception('Deve essere specificato il cespite aziendale o l\'identificativo.');
      }
    });

    // Aggiunge un log quando viene creato un nuovo hardware
    static::created(function ($model) {
        // if ($model->company_id != null) {
            HardwareAuditLog::create([
              'log_subject' => 'hardware',
              'log_type' => 'create',
              'modified_by' => auth()->id(),
              'hardware_id' => $model->id,
              'old_data' => null,
              'new_data' => json_encode($model->toArray()),
            ]);
        // }
    });

    // Aggiunge un log quando viene modificato un hardware
    static::updating(function ($model) {
      
      $model->updated_at = now();

      $originalData = $model->getOriginal();
      $updatedData = $model->toArray();

      if (!$updatedData['company_asset_number'] && !$updatedData['support_label']) {
        throw new \Exception('Deve essere specificato il cespite aziendale o l\'identificativo.');
      }

      // Trasforma l'eventuale array di oggetti "users" in array di numeri (id). gli altri li toglie perchè non sono previsti.
      foreach ($updatedData as $key => $value) {
        if (is_array($value) && isset($value[0]) && is_object($value[0])) {
          if($key == 'users') {
            $updatedData[$key] = array_map(function ($item) {
              return $item->id;
            }, $value);
          } else {
            unset($updatedData[$key]);
          }
        }
      }

      HardwareAuditLog::create([
        'log_subject' => 'hardware',
        'log_type' => 'update',
        'modified_by' => auth()->id(),
        'hardware_id' => $model->id,
        'old_data' => json_encode($originalData),
        'new_data' => json_encode($updatedData),
      ]);
    });

    // Aggiunge un log quando viene eliminato un hardware (soft delete)
    static::deleting(function ($model) {
      $deleteType = $model->isForceDeleting() ? 'force_delete' : 'soft_delete';

      $model->users = $model->users()->pluck('user_id')->toArray();
      HardwareAuditLog::create([
        'log_subject' => 'hardware',
        'log_type' => $deleteType,
        'modified_by' => auth()->id(),
        'hardware_id' => $model->id,
        'old_data' => json_encode($model->toArray()),
        'new_data' => null,
      ]);
    });

    // Aggiunge un log quando viene ripristinato un hardware
    static::restored(function ($model) {
      $model->users = $model->users()->pluck('user_id')->toArray();
      HardwareAuditLog::create([
        'log_subject' => 'hardware',
        'log_type' => 'restore',
        'modified_by' => auth()->id(),
        'hardware_id' => $model->id,
        'old_data' => null,
        'new_data' => json_encode($model->toArray()),
      ]);
    });
  }

  public function company() {
    return $this->belongsTo(Company::class);
  }

  public function hardwareType() {
    return $this->belongsTo(HardwareType::class);
  }

  // public function users() {
  //   return $this->belongsToMany(User::class);
  // }
  public function users() {
    return $this->belongsToMany(User::class, 'hardware_user')
      ->using(HardwareUser::class)
      ->withPivot('created_by', 'responsible_user_id', 'created_at', 'updated_at');
  }

  public function tickets() {
    return $this->belongsToMany(Ticket::class);
  }

}

