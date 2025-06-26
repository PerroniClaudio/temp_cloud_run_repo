<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Ticket extends Model {
    use HasFactory, Searchable;

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'description',
        'type',
        'file',
        'duration',
        'admin_user_id',
        'group_id',
        'due_date',
        'type_id',
        'sla_take',
        'sla_solve',
        'priority',
        'wait_end',
        'is_user_error',
        'unread_mess_for_adm',
        'unread_mess_for_usr',
        'actual_processing_time',
        'is_form_correct',
        'was_user_self_sufficient',
        'is_user_error_problem',
        'work_mode',
        'source',
        'is_rejected',
        'parent_ticket_id',
        'is_billable',
        'master_id',
        'reopen_parent_id'
    ];

    public function toSearchableArray() {
        return [
            'description' => $this->description,
            'status' => $this->status,
            'type' => $this->type,
            'user_name' => $this->user->name,
            'user_surname' => $this->user->surname,
            'company' => $this->company->name,
        ];
    }

    /* get the owner */

    public function user() {
        return $this->belongsTo(User::class);
    }

    /* get the group */
    public function group() {
        return $this->belongsTo(Group::class);
    }

    /* get the handler */

    public function handler() {
        // return User::find($this->admin_user_id);
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /* get the referer (utente interessato) */

    public function referer() {
        // Si usa newQueryWithoutRelationships per evitare di caricare i messaggi, che non servono
        $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
        if (!!$ticketWithoutMessages) {
            $messages = $ticketWithoutMessages->messages;
            if (count($messages) > 0) {
                $message_obj = json_decode($messages[0]->message);
                // Controllo se esiste la proprietà, perchè nei ticket vecchi non c'è e può dare errore.
                if (isset($message_obj->referer)) {
                    return User::find($message_obj->referer);
                }
            }
        }
        return User::find(0);
    }

    /* get the IT referer (referente IT) */

    public function refererIT() {
        $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
        $messages = $ticketWithoutMessages->messages;
        if (count($messages) > 0) {
            $message_obj = json_decode($messages[0]->message);
            // Controllo se esiste la proprietà, perchè nei ticket vecchi non c'è e può dare errore.
            if (isset($message_obj->referer_it)) {
                return User::find($message_obj->referer_it);
            }
        }
        return User::find(0);
    }

    public function hardware() {
        return $this->belongsToMany(Hardware::class);
    }

    /** get  messages  */

    public function messages() {
        return $this->hasMany(TicketMessage::class);
    }

    /** get  status updates  */

    public function statusUpdates() {
        return $this->hasMany(TicketStatusUpdate::class);
    }

    public function ticketType() {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function files() {
        return $this->hasMany(TicketFile::class);
    }

    public function brandUrl() {
        $brand_id = $this->ticketType->brand->id;
        return env('APP_URL') . '/api/brand/' . $brand_id . '/logo';
    }

    // Invalida la cache per chi ha creato il ticket e per i referenti
    public function invalidateCache() {
        // $cacheKey = 'user_' . $user->id . '_tickets';
        $ticketUser = $this->user;
        $referer = $this->referer();
        $refererIT = $this->refererIT();
        if ($ticketUser) {
            Cache::forget('user_' . $ticketUser->id . '_tickets');
            Cache::forget('user_' . $ticketUser->id . '_tickets_with_closed');
        }
        if ($referer) {
            Cache::forget('user_' . $referer->id . '_tickets');
            Cache::forget('user_' . $referer->id . '_tickets_with_closed');
        }
        if ($refererIT) {
            Cache::forget('user_' . $refererIT->id . '_tickets');
            Cache::forget('user_' . $refererIT->id . '_tickets_with_closed');
        }
    }

    // In base al tipo di ticket si dovranno includere o meno i sabati, le domeniche, tutte le ore del giorno o anche le festività 
    public function waitingHours($includeSaturday = false, $includeSunday = false, $IncludeAllDayHours = false, $includeHolidays = false) {
        $waitingHours = 0;

        // Array delle festività italiane
        $holidays = [
            '01-01', // Capodanno
            '06-01', // Epifania
            '25-04', // Festa della Liberazione
            '01-05', // Festa dei Lavoratori
            '02-06', // Festa della Repubblica
            '15-08', // Ferragosto
            '01-11', // Ognissanti
            '08-12', // Immacolata Concezione
            '25-12', // Natale
            '26-12', // Santo Stefano
        ];

        /*
            Se il ticket è stato in attesa almeno una volta bisogna calcolare il tempo totale in cui è rimasto in attesa.
        */

        $statusUpdates = $this->statusUpdates()->whereIn('type', ['status', 'closing'])->get();

        $hasBeenWaiting = false;
        $waitingRecords = [];
        $waitingEndingRecords = [];
        $waitingMinutes = 0;

        for ($i = 0; $i < count($statusUpdates); $i++) {
            if (
                (strpos(strtolower($statusUpdates[$i]->content), 'in attesa') !== false) || (strpos(strtolower($statusUpdates[$i]->content), 'risolto') !== false)
            ) {
                $hasBeenWaiting = true;
                $waitingRecords[] = $statusUpdates[$i];
                $waitingEndingRecords[] = $statusUpdates[$i + 1] ?? null;
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        for ($i = 0; $i < count($waitingRecords); $i++) {
            $start = $waitingRecords[$i]->created_at;
            $end = $waitingEndingRecords[$i] != null ? $waitingEndingRecords[$i]->created_at : now();
            $totalMinutes = $start->diffInMinutes($end);

            $excludedMinutes = 0;
            $current = $start->copy();

            while ($current->lessThan($end)) {
                $isExcludedDay = (!$includeSunday && $current->isSunday())
                    || (!$includeSaturday && $current->isSaturday())
                    || (!$includeHolidays && in_array($current->format('m-d'), $holidays));
                $isExcludedHour = !$IncludeAllDayHours && ($current->hour >= 20 || $current->hour < 8);
                if ($isExcludedHour || $isExcludedDay) {
                    $excludedMinutes++;
                }
                $current->addMinute();
            }

            $waitingMinutes += ($totalMinutes - $excludedMinutes);
        }

        $waitingHours = $waitingMinutes / 60;

        return $waitingHours;
    }

    public function waitingTimes() {
        $waitingHours = 0;

        /*
            Se il ticket è stato in attesa almeno una volta bisogna calcolare il tempo totale in cui è rimasto in attesa.
        */

        $statusUpdates = $this->statusUpdates()->where('type', 'status')->get();

        $hasBeenWaiting = false;
        $waitingRecords = [];
        $waitingEndingRecords = [];

        for ($i = 0; $i < count($statusUpdates); $i++) {
            if (
                (strpos(strtolower($statusUpdates[$i]->content), 'in attesa') !== false) || (strpos(strtolower($statusUpdates[$i]->content), 'risolto') !== false)
            ) {
                $hasBeenWaiting = true;
                $waitingRecords[] = $statusUpdates[$i];
                if (count($statusUpdates) > ($i + 1)) {
                    $waitingEndingRecords[] = $statusUpdates[$i + 1];
                }
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        return count($waitingRecords);
    }

    public function parent() {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    // public function children() {
    //     return $this->hasMany(Ticket::class, 'parent_ticket_id');
    // }

    // Dato che c'è già parent (e child/children non c'è ma sarebbe il suo corrispettivo), 
    // per il collegamento tra ticket on site e ticket normali uso master e slave (un ticket on site può avere da 0 a n ticket normali a lui collegati e questi due vengono trattati diversamente nel report).
    public function master() {
        return $this->belongsTo(Ticket::class, 'master_id');
    }

    public function slaves() {
        return $this->hasMany(Ticket::class, 'master_id');
    }

    public function reopenedParent() {
        return $this->belongsTo(Ticket::class, 'reopen_parent_id');
    }
    
    public function reopenedChild() {
        return $this->hasOne(Ticket::class, 'reopen_parent_id');
    }

}
