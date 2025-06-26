@component('mail::message')
## Assegnazione {{ $category->is_problem ? 'Incident' : 'Request' }} {{ $ticket->id }}

{{ $category->is_problem ? 'Ti è stato assegnato un Incident' : 'Ti è stata assegnata una Request' }}
<!-- da {{ $user->name }} -->

{{ $category->is_problem ? 'Incident' : 'Request' }} n° {{ $ticket->id }} <br>
Azienda: {{ $company->name }} <br>
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br><br>
<!-- Update: {{ $update->content }} <br><br> -->
Stato:
@component('mail::status', ['status' => $ticket->status, 'stages' => $stages])
@endcomponent

<br>
@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent