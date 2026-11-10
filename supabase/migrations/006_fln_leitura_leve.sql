-- Tira o documento grande do caminho quente.
-- Logos ficam em fln_privado.midia e só saem quando o navegador pede a imagem.
-- Avisos, chat em fundo e mapa não leem mais o JSON inteiro.
-- fln_salvar preserva chat.mensagens quando a leitura leve não as enviou.

create table if not exists fln_privado.midia (
  base smallint not null,
  chave text not null check (chave in ('logo', 'logo_lateral', 'favicon', 'assinatura')),
  valor text not null,
  primary key (base, chave)
);

alter table fln_privado.midia enable row level security;
revoke all on fln_privado.midia from public, anon, authenticated;

insert into fln_privado.midia (base, chave, valor)
select e.id, c.chave, e.dados #>> array['settings', c.chave]
from fln_privado.estado e
cross join (values ('logo'), ('logo_lateral'), ('favicon'), ('assinatura')) as c(chave)
where coalesce(e.dados #>> array['settings', c.chave], '') <> ''
on conflict (base, chave) do update set valor = excluded.valor;

update fln_privado.estado e
set dados = jsonb_set(
  jsonb_set(
    jsonb_set(
      jsonb_set(
        jsonb_set(
          e.dados
            #- '{settings,logo}'
            #- '{settings,logo_lateral}'
            #- '{settings,favicon}'
            #- '{settings,assinatura}',
          array['settings', 'tem_logo'],
          to_jsonb(exists (select 1 from fln_privado.midia m where m.base = e.id and m.chave = 'logo' and m.valor <> '')),
          true
        ),
        array['settings', 'tem_logo_lateral'],
        to_jsonb(exists (select 1 from fln_privado.midia m where m.base = e.id and m.chave = 'logo_lateral' and m.valor <> '')),
        true
      ),
      array['settings', 'tem_favicon'],
      to_jsonb(exists (select 1 from fln_privado.midia m where m.base = e.id and m.chave = 'favicon' and m.valor <> '')),
      true
    ),
    array['settings', 'tem_assinatura'],
    to_jsonb(exists (select 1 from fln_privado.midia m where m.base = e.id and m.chave = 'assinatura' and m.valor <> '')),
    true
  ),
  array['settings', 'marca_v'],
  to_jsonb(coalesce((e.dados #>> '{settings,marca_v}')::int, 1)),
  true
);

create or replace function public.fln_salvar(
  p_segredo text,
  p_versao bigint,
  p_dados jsonb,
  p_acao text,
  p_usuario text,
  p_base smallint default 1
)
returns bigint
language plpgsql
security definer
set search_path to ''
as $function$
declare
  atual jsonb;
  v bigint;
  k text;
  img text;
  imagem text;
  mexeu_imagem boolean := false;
begin
  perform set_config('statement_timeout', '180s', true);
  perform fln_privado.conferir(p_segredo);
  if p_base not in (1, 2) then
    raise exception 'base inválida' using errcode = '22023';
  end if;
  if p_dados is null or jsonb_typeof(p_dados) <> 'object'
     or jsonb_typeof(p_dados -> 'transfers') <> 'array'
     or jsonb_typeof(p_dados -> 'motoristas') <> 'array'
     or jsonb_typeof(p_dados -> 'lancamentos') <> 'array'
     or jsonb_typeof(p_dados -> 'settings') <> 'object' then
    raise exception 'estado inválido' using errcode = '22023';
  end if;

  if p_versao <> 0 then
    select dados into atual from fln_privado.estado where id = p_base and versao = p_versao for update;
    if not found then
      return -1;
    end if;
    foreach k in array array['transfers', 'lancamentos', 'motoristas', 'clientes', 'veiculos'] loop
      if fln_privado.contar(p_dados, k) < fln_privado.contar(atual, k) - 3 then
        raise exception 'Bloqueado: a gravação removeria vários registros de % de uma vez.', k using errcode = '22023';
      end if;
    end loop;
    if not (p_dados ? 'chat') then
      p_dados := jsonb_set(p_dados, array['chat'], coalesce(atual -> 'chat', '{}'::jsonb), true);
    elsif not (p_dados -> 'chat' ? 'mensagens') then
      p_dados := jsonb_set(
        p_dados,
        array['chat', 'mensagens'],
        coalesce(atual -> 'chat' -> 'mensagens', '{}'::jsonb),
        true
      );
    end if;
  end if;

  foreach img in array array['logo', 'logo_lateral', 'favicon', 'assinatura'] loop
    if p_dados -> 'settings' ? img then
      imagem := coalesce(p_dados #>> array['settings', img], '');
      if imagem <> '' then
        mexeu_imagem := true;
        insert into fln_privado.midia (base, chave, valor)
        values (p_base, img, imagem)
        on conflict (base, chave) do update set valor = excluded.valor;
      elsif p_acao = 'remove_logo' then
        mexeu_imagem := true;
        delete from fln_privado.midia where base = p_base and chave = img;
      end if;
      p_dados := p_dados #- array['settings', img];
    end if;
    p_dados := jsonb_set(
      p_dados,
      array['settings', 'tem_' || img],
      to_jsonb(exists (
        select 1 from fln_privado.midia m
        where m.base = p_base and m.chave = img and m.valor <> ''
      )),
      true
    );
  end loop;

  if mexeu_imagem then
    p_dados := jsonb_set(
      p_dados,
      array['settings', 'marca_v'],
      to_jsonb(coalesce((atual #>> '{settings,marca_v}')::int, 0) + 1),
      true
    );
  end if;

  if p_versao = 0 then
    insert into fln_privado.estado (id, versao, dados, atualizado_por)
    values (p_base, 1, p_dados, left(coalesce(p_usuario, ''), 80))
    on conflict (id) do nothing
    returning versao into v;
    if v is null then
      return -1;
    end if;
  else
    update fln_privado.estado
       set versao = versao + 1, dados = p_dados, atualizado_em = now(),
           atualizado_por = left(coalesce(p_usuario, ''), 80)
     where id = p_base
    returning versao into v;
  end if;

  insert into fln_privado.historico (base, versao, acao, usuario, dados)
  values (
    p_base,
    v,
    left(coalesce(p_acao, ''), 60),
    left(coalesce(p_usuario, ''), 80),
    p_dados #- '{chat,mensagens}'
  );
  return v;
end
$function$;

create or replace function public.fln_ler_leve(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  r record;
begin
  perform fln_privado.conferir(p_segredo);
  select versao, dados into r from fln_privado.estado where id = p_base;
  if not found then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;
  return jsonb_build_object('versao', r.versao, 'dados', r.dados #- '{chat,mensagens}');
end
$function$;

create or replace function public.fln_avisos(
  p_segredo text,
  p_desde bigint,
  p_base smallint default 1
)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  doc jsonb;
  item jsonb;
  itens jsonb := '[]'::jsonb;
  ultimo bigint := greatest(coalesce(p_desde, 0), 0);
  idv bigint;
begin
  perform fln_privado.conferir(p_segredo);
  select coalesce(dados -> 'avisos', '[]'::jsonb) into doc
  from fln_privado.estado where id = p_base;
  if doc is null or jsonb_typeof(doc) <> 'array' then
    return jsonb_build_object('ultimo', ultimo, 'itens', '[]'::jsonb);
  end if;
  for item in select value from jsonb_array_elements(doc) loop
    idv := coalesce((item ->> 'id')::bigint, 0);
    if idv > ultimo then
      ultimo := idv;
    end if;
    if idv > coalesce(p_desde, 0) then
      itens := itens || jsonb_build_array(item);
    end if;
  end loop;
  return jsonb_build_object('ultimo', ultimo, 'itens', itens);
end
$function$;

create or replace function public.fln_aviso_apk(
  p_segredo text,
  p_token text,
  p_desde bigint,
  p_base smallint default 1
)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  doc jsonb;
  push jsonb;
  elem jsonb;
  i int;
  achou boolean := false;
  gravar boolean := false;
  avisos jsonb;
  ultimo bigint;
  visto_novo text;
begin
  perform fln_privado.conferir(p_segredo);
  p_token := lower(regexp_replace(coalesce(p_token, ''), '[^a-f0-9]', '', 'g'));
  if length(p_token) < 32 or p_base not in (1, 2) then
    return jsonb_build_object('ok', false, 'erro', 'token');
  end if;

  select dados into doc from fln_privado.estado where id = p_base for update;
  if doc is null then
    return jsonb_build_object('ok', false, 'erro', 'aparelho');
  end if;

  avisos := public.fln_avisos(p_segredo, p_desde, p_base);
  ultimo := coalesce((avisos ->> 'ultimo')::bigint, greatest(coalesce(p_desde, 0), 0));
  visto_novo := to_char(now() at time zone 'America/Sao_Paulo', 'YYYY-MM-DD HH24:MI');
  push := coalesce(doc -> 'push', '[]'::jsonb);
  if jsonb_typeof(push) <> 'array' then
    return jsonb_build_object('ok', false, 'erro', 'aparelho');
  end if;

  for i in 0 .. jsonb_array_length(push) - 1 loop
    elem := push -> i;
    if coalesce(elem ->> 'kind', '') = 'apk'
       and lower(coalesce(elem ->> 'token', '')) = p_token then
      achou := true;
      if (elem ->> 'desde') is distinct from ultimo::text
         or coalesce(elem ->> 'visto_em', '') < to_char(
           (now() at time zone 'America/Sao_Paulo') - interval '10 minutes',
           'YYYY-MM-DD HH24:MI'
         ) then
        elem := elem || jsonb_build_object('desde', ultimo, 'visto_em', visto_novo);
        push := jsonb_set(push, array[i::text], elem, false);
        gravar := true;
      end if;
    end if;
  end loop;

  if not achou then
    return jsonb_build_object('ok', false, 'erro', 'aparelho');
  end if;
  if gravar then
    update fln_privado.estado
       set dados = jsonb_set(dados, array['push'], push, true)
     where id = p_base;
  end if;
  return jsonb_build_object(
    'ok', true,
    'itens', coalesce(avisos -> 'itens', '[]'::jsonb),
    'ultimo', ultimo
  );
end
$function$;

create or replace function public.fln_chat_resumo(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  doc jsonb := '{}'::jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select dados into doc from fln_privado.estado where id = p_base;
  if doc is null then
    doc := '{}'::jsonb;
  end if;
  return jsonb_build_object(
    'settings', jsonb_build_object(
      'wa_status', coalesce(doc #>> '{settings,wa_status}', ''),
      'wa_profile_name', coalesce(doc #>> '{settings,wa_profile_name}', ''),
      'uazapi_instance_name', coalesce(doc #>> '{settings,uazapi_instance_name}', ''),
      'uazapi_url', coalesce(doc #>> '{settings,uazapi_url}', ''),
      'uazapi_token', coalesce(doc #>> '{settings,uazapi_token}', ''),
      'chat_ignorar_grupos', coalesce(doc #> '{settings,chat_ignorar_grupos}', 'true'::jsonb)
    ),
    'chat', jsonb_build_object(
      'conversas', coalesce(doc #> '{chat,conversas}', '{}'::jsonb),
      'digitando', coalesce(doc #> '{chat,digitando}', '{}'::jsonb),
      'desde', coalesce(doc #> '{chat,desde}', '0'::jsonb),
      'secret', coalesce(doc #>> '{chat,secret}', '')
    )
  );
end
$function$;

create or replace function public.fln_mapa(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  saida jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select coalesce(jsonb_agg(jsonb_build_object(
    'id', t -> 'id',
    'status', coalesce(t ->> 'status', ''),
    'data', coalesce(t ->> 'data', ''),
    'hora', coalesce(t ->> 'hora', ''),
    'origem', coalesce(t ->> 'origem', ''),
    'destino', coalesce(t ->> 'destino', ''),
    'cliente', coalesce(t ->> 'cliente', '')
  )), '[]'::jsonb)
  into saida
  from fln_privado.estado e,
       jsonb_array_elements(coalesce(e.dados -> 'transfers', '[]'::jsonb)) t
  where e.id = p_base;
  return coalesce(saida, '[]'::jsonb);
end
$function$;

create or replace function public.fln_midia(
  p_segredo text,
  p_base smallint,
  p_chave text
)
returns text
language plpgsql
security definer
set search_path to ''
as $function$
declare
  v text;
begin
  perform fln_privado.conferir(p_segredo);
  if p_chave not in ('logo', 'logo_lateral', 'favicon', 'assinatura') then
    return '';
  end if;
  select valor into v from fln_privado.midia where base = p_base and chave = p_chave;
  return coalesce(v, '');
end
$function$;

revoke all on function public.fln_ler_leve(text, smallint) from public;
revoke all on function public.fln_avisos(text, bigint, smallint) from public;
revoke all on function public.fln_aviso_apk(text, text, bigint, smallint) from public;
revoke all on function public.fln_chat_resumo(text, smallint) from public;
revoke all on function public.fln_mapa(text, smallint) from public;
revoke all on function public.fln_midia(text, smallint, text) from public;

grant execute on function public.fln_ler_leve(text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_avisos(text, bigint, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_aviso_apk(text, text, bigint, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_chat_resumo(text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_mapa(text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_midia(text, smallint, text) to anon, authenticated, service_role;
