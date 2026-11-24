-- Leitura automática: ontem até daqui a sete dias, sem o histórico de mensagens.
-- O cron só baixa a lista de novo quando alguém grava e a versão muda.

do $mig$
declare
  src text;
  alvo regprocedure;
begin
  alvo := 'public.fln_ler_menu(text,smallint,text,text,text,integer,bigint)'::regprocedure;
  src := pg_get_functiondef(alvo);
  src := replace(
    src,
    'p_de := to_char(date_trunc(''month'', hoje)::date, ''YYYY-MM-DD'');' || chr(10) ||
    '      p_ate := to_char((date_trunc(''month'', hoje) + interval ''1 month'' - interval ''1 day'')::date, ''YYYY-MM-DD'');',
    'p_de := to_char(hoje - 1, ''YYYY-MM-DD'');' || chr(10) ||
    '      p_ate := to_char(hoje + 7, ''YYYY-MM-DD'');'
  );
  src := replace(src, '#- ''{chat,mensagens}''', '#- ''{chat,mensagens}'' #- ''{mensagens}''');
  execute src;
end
$mig$;

do $mig$
declare
  src text;
  trecho text := 'p_dados := p_dados - ''transfers'' - ''lancamentos'' - ''_menu'';';
  novo text := $sql$
    if jsonb_typeof(atual -> 'mensagens') = 'array'
       and jsonb_array_length(atual -> 'mensagens') > 0
       and (
         not (p_dados ? 'mensagens')
         or jsonb_typeof(p_dados -> 'mensagens') <> 'array'
         or jsonb_array_length(p_dados -> 'mensagens') = 0
       ) then
      p_dados := jsonb_set(p_dados, '{mensagens}', atual -> 'mensagens', true);
    end if;
    p_dados := p_dados - 'transfers' - 'lancamentos' - '_menu';
  $sql$;
begin
  src := pg_get_functiondef('public.fln_salvar(text,bigint,jsonb,text,text,smallint)'::regprocedure);
  if position('atual -> ''mensagens''' in src) = 0 then
    if position(trecho in src) = 0 then
      raise exception 'ponto de preservar mensagens não encontrado';
    end if;
    execute replace(src, trecho, novo);
  end if;
end
$mig$;

create table if not exists fln_privado.eco (
  chave text primary key,
  versao bigint not null,
  em timestamptz not null default now()
);

revoke all on table fln_privado.eco from public, anon, authenticated;

create or replace function public.fln_eco(p_segredo text, p_chave text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  versao bigint;
  marcada bigint;
  ajustes jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select e.versao, e.dados -> 'settings'
    into versao, ajustes
    from fln_privado.estado e
   where e.id = p_base;
  select o.versao into marcada
    from fln_privado.eco o
   where o.chave = left(coalesce(p_chave, ''), 40);
  if versao is not null and marcada is not null and marcada = versao then
    return jsonb_build_object('igual', true, 'versao', versao, 'settings', coalesce(ajustes, '{}'::jsonb));
  end if;
  return jsonb_build_object('igual', false, 'versao', coalesce(versao, 0));
end
$function$;

create or replace function public.fln_eco_marcar(p_segredo text, p_chave text, p_base smallint default 1)
returns void
language plpgsql
security definer
set search_path to ''
as $function$
begin
  perform fln_privado.conferir(p_segredo);
  insert into fln_privado.eco (chave, versao, em)
  select left(coalesce(p_chave, ''), 40), e.versao, pg_catalog.now()
    from fln_privado.estado e
   where e.id = p_base
  on conflict (chave) do update
    set versao = excluded.versao,
        em = excluded.em;
end
$function$;

revoke all on function public.fln_eco(text, text, smallint) from public;
revoke all on function public.fln_eco_marcar(text, text, smallint) from public;
grant execute on function public.fln_eco(text, text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_eco_marcar(text, text, smallint) to anon, authenticated, service_role;

notify pgrst, 'reload schema';
