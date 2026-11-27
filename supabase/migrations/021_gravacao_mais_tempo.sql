-- A gravação da reserva às vezes era cancelada com statement timeout.
-- O tempo sobe só nestas duas funções, que já limitam o próprio tempo.

do $mig$
declare
  src text;
begin
  src := pg_get_functiondef('public.fln_ler_gravacao(text,smallint,integer,text,text)'::regprocedure);
  if position('statement_timeout'', ''25s''' in src) > 0 then
    src := replace(src, 'statement_timeout'', ''25s''', 'statement_timeout'', ''50s''');
    execute src;
  end if;

  src := pg_get_functiondef('public.fln_gravar_reservas(text,bigint,jsonb,jsonb,jsonb,text,text,smallint,jsonb)'::regprocedure);
  if position('statement_timeout'', ''60s''' in src) > 0 then
    src := replace(src, 'statement_timeout'', ''60s''', 'statement_timeout'', ''90s''');
    execute src;
  end if;
end
$mig$;
