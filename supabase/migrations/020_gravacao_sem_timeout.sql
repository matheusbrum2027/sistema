-- A leitura da reserva para salvar varria o documento sem aumentar o tempo.
-- No plano pequeno isso estourava com "canceling statement due to statement timeout".

do $mig$
declare
  src text;
begin
  src := pg_get_functiondef('public.fln_ler_gravacao(text,smallint,integer,text,text)'::regprocedure);
  if position('statement_timeout' in src) = 0 then
    src := replace(
      src,
      'begin',
      E'begin\n  perform set_config(''statement_timeout'', ''25s'', true);'
    );
    execute src;
  end if;
end
$mig$;
