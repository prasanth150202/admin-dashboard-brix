{!! $reply->body !!}

--
{!! $reply->user?->name !!}
{!! config('app.name') !!} support · ticket {{ $reply->ticket->number() }}
Reply to this email to answer — keep "{{ $reply->ticket->mailTag() }}" in the subject.
