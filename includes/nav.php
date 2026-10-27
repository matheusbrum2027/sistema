<?php
declare(strict_types=1);

function nav_item(string $id, string $title, string $icon, string $subtitle, string $view, bool $filters = false): array
{
    return [
        'id' => $id,
        'title' => $title,
        'icon' => $icon,
        'subtitle' => $subtitle,
        'view' => $view,
        'filters' => $filters,
    ];
}

function navigation(): array
{
    return [
        [
            'label' => 'Principal',
            'groups' => [
                [
                    'id' => 'painel',
                    'label' => 'Painel',
                    'icon' => 'grid',
                    'items' => [
                        nav_item('agenda', 'Agenda', 'calendar', 'Viagens programadas no calendário.', 'views/agenda.php'),
                        nav_item('metricas', 'Métricas', 'activity', 'Indicadores de performance e KPIs do seu negócio. Atualização automática a cada 60 s.', 'views/metricas.php', true),
                    ],
                ],
            ],
        ],
        [
            'label' => null,
            'groups' => [
                [
                    'id' => 'financeiro',
                    'label' => 'Financeiro',
                    'icon' => 'folder',
                    'items' => [
                        nav_item('fin-dashboard', 'Dashboard', 'chart', 'Caixa, recebíveis e despesas da operação.', 'views/fin-dashboard.php'),
                        nav_item('lancamentos', 'Lançamentos', 'list', 'Entradas e saídas registradas.', 'views/lancamentos.php'),
                        nav_item('receber', 'Contas a receber', 'inbox', 'Viagens ainda não recebidas.', 'views/receber.php'),
                        nav_item('faturado', 'Faturado', 'receipt', 'Faturas emitidas das viagens.', 'views/faturado.php'),
                        nav_item('pagar', 'Contas a pagar', 'card', 'Despesas e contas em aberto.', 'views/pagar.php'),
                        nav_item('relatorios', 'Relatórios', 'bars', 'Consolidados do período para consulta e impressão.', 'views/relatorios.php'),
                    ],
                ],
                [
                    'id' => 'transfer',
                    'label' => 'Transfer',
                    'icon' => 'route',
                    'items' => [
                        nav_item('solicitacoes', 'Solicitações', 'file', 'Registros recebidos via webhook do site.', 'views/solicitacoes.php'),
                        nav_item('reservas', 'Reservas', 'book', 'Reservas de transfer da operação.', 'views/reservas.php'),
                        nav_item('contrato', 'Contrato', 'file', 'Modelo e contratos gerados para as viagens.', 'views/contrato.php'),
                    ],
                ],
                [
                    'id' => 'motoristas',
                    'label' => 'Motoristas',
                    'icon' => 'user',
                    'items' => [
                        nav_item('mot-cadastros', 'Cadastros', 'users', 'Condutores da frota e acesso ao painel do motorista.', 'views/motoristas.php'),
                        nav_item('mot-solicitacoes', 'Solicitações', 'file', 'Candidatos enviados pelo webhook Motorista.', 'views/mot-solicitacoes.php'),
                    ],
                ],
                [
                    'id' => 'clientes',
                    'label' => 'Clientes',
                    'icon' => 'id',
                    'direct' => true,
                    'items' => [
                        nav_item('clientes', 'Clientes', 'id', 'Cadastros usados nas reservas de transfer e grupos.', 'views/clientes.php'),
                    ],
                ],
                [
                    'id' => 'veiculos',
                    'label' => 'Veículos',
                    'icon' => 'car',
                    'direct' => true,
                    'items' => [
                        nav_item('veiculos', 'Veículos', 'car', 'Frota para o cálculo das corridas.', 'views/veiculos.php'),
                    ],
                ],
            ],
        ],
        [
            'label' => 'Configurações',
            'groups' => [
                [
                    'id' => 'sistema',
                    'label' => 'Sistema',
                    'icon' => 'gear',
                    'items' => [
                        nav_item('configuracoes', 'Configurações', 'sliders', 'Dados da empresa e da operação.', 'views/configuracoes.php'),
                        nav_item('automacoes', 'Automações', 'zap', 'Regras locais disparadas pela mudança de status das viagens.', 'views/automacoes.php'),
                        nav_item('comunicador', 'Comunicador', 'monitor', 'Modelos de mensagem e registros gerados neste computador.', 'views/comunicador.php'),
                        nav_item('anotacoes', 'Anotações', 'note', 'Lembretes internos da equipe.', 'views/anotacoes.php'),
                    ],
                ],
            ],
        ],
    ];
}

function route_map(): array
{
    $map = [];
    foreach (navigation() as $section) {
        foreach ($section['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $item['group'] = $group['label'];
                $map[$item['id']] = $item;
            }
        }
    }
    $map['entrar'] = [
        'id' => 'entrar',
        'title' => 'Entrar',
        'icon' => 'logout',
        'subtitle' => '',
        'view' => 'views/entrar.php',
        'filters' => false,
        'group' => '',
        'bare' => true,
    ];
    return $map;
}
