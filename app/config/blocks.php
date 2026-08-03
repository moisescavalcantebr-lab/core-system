<?php

declare(strict_types=1);

/* =========================
FUNÇÃO (PROTEGIDA)
========================= */

if (!function_exists('getAllowedBlocks')) {
    function getAllowedBlocks(string $type): array
    {
        $map = [

            'page' => [
                'hero',
                'benefits_cards',
                'text',
                'catalog_products',
                'cta_whatsapp',
                'lead_form',
                'testimonials',
                'cta_button'
            ],

            'blog' => [
                'blog_header',
                'blog_content',
                'blog_text',
                'blog_list',
                'blog_image',
                'blog_quote',
                'blog_cta',
                'blog_video'
            ]

        ];

        return $map[$type] ?? [];
    }
}

/* =========================
BLOCKS
========================= */

return [

    /* ================= PAGE ================= */

    'hero' => [
        'label' => 'Hero',
        'category' => 'layout',
        'fields' => [
            'title' => [
                'type' => 'text',
                'label' => 'Titulo',
                'default' => 'Transforme visitantes em clientes'
            ],
            'subtitle' => [
                'type' => 'textarea',
                'label' => 'Subtitulo',
                'default' => 'Uma landing page rápida, profissional e focada em conversão.'
            ],
            'cta_text' => [
                'type' => 'text',
                'label' => 'Texto do botão',
                'default' => 'Começar agora'
            ],
            'cta_enabled' => [
                'type' => 'text',
                'label' => 'Exibir botão',
                'default' => '0'
            ],
            'cta_url' => [
                'type' => 'text',
                'label' => 'URL do botão',
                'default' => ''
            ],
            'cta_target' => [
                'type' => 'select',
                'label' => 'Abrir botão',
                'options' => [
                    '_self' => 'Mesma aba',
                    '_blank' => 'Nova aba'
                ],
                'default' => '_self'
            ],
        ]
    ],

    'benefits_cards' => [
        'label' => 'Beneficios',
        'fields' => [

            'title' => [
                'type' => 'text',
                'label' => 'Titulo da seção',
                'default' => 'Por que escolher nossa solução?'
            ],

            'cards' => [
                'type' => 'group',
                'label' => 'Cards',
                'fields' => [

                    'icon' => [
                        'type' => 'select',
                        'label' => 'icone',
                        'options' => [
                            'star' => '⭐ Star',
                            'check' => '✔ Check',
                            'rocket' => '🚀 Rocket',
                            'shield' => '🛡 Shield',
                            'bolt' => '⚡ Bolt',
                            'heart' => '❤️ Heart',
                            'users' => '👥 Users',
                            'chart' => '📊 Chart'
                        ]
                    ],

                    'title' => [
                        'type' => 'text',
                        'label' => 'Titulo'
                    ],

                    'text' => [
                        'type' => 'textarea',
                        'label' => 'Texto'
                    ]

                ],

                'default' => [
                    [
                        'icon' => 'star',
                        'title' => 'Estrutura profissional',
                        'text' => 'Layout pensado para gerar confiança e ação.'
                    ],
                    [
                        'icon' => 'check',
                        'title' => 'Contato imediato',
                        'text' => 'WhatsApp e formulário integrados.'
                    ],
                    [
                        'icon' => 'rocket',
                        'title' => 'Totalmente configurável',
                        'text' => 'Edite tudo diretamente pelo painel.'
                    ]
                ]
            ]

        ]
    ],

    'text' => [
        'label' => 'Texto',
        'category' => 'content',

        'fields' => [

            'title' => [
                'label' => 'Título',
                'type' => 'text',
                'default' => ''
            ],

            'content' => [
                'label' => 'Conteúdo',
                'type' => 'textarea',
                'default' => ''
            ],

            'align' => [
                'label' => 'Alinhamento',
                'type' => 'select',
                'options' => [
                    'left' => 'Esquerda',
                    'center' => 'Centro',
                    'right' => 'Direita'
                ],
                'default' => 'left'
            ]
        ]
    ],

    'catalog_products' => [
        'label' => 'Catálogo',
        'category' => 'content',

        'fields' => [

            'title' => [
                'label' => 'Título',
                'type' => 'text',
                'default' => 'Galeria'
            ],

            'image_1' => [
                'label' => 'Imagem 1 (URL da Biblioteca)',
                'type' => 'text',
                'default' => ''
            ],
            'title_1' => [
                'label' => 'Título 1',
                'type' => 'text',
                'default' => 'Primeiro destaque'
            ],
            'description_1' => [
                'label' => 'Descrição 1',
                'type' => 'textarea',
                'default' => 'Use este card para mostrar uma imagem importante da pagina.'
            ],

            'image_2' => [
                'label' => 'Imagem 2 (URL da Biblioteca)',
                'type' => 'text',
                'default' => ''
            ],
            'title_2' => [
                'label' => 'Título 2',
                'type' => 'text',
                'default' => 'Segundo destaque'
            ],
            'description_2' => [
                'label' => 'Descrição 2',
                'type' => 'textarea',
                'default' => 'Cole aqui o link da imagem enviada para a Biblioteca.'
            ],

            'image_3' => [
                'label' => 'Imagem 3 (URL da Biblioteca)',
                'type' => 'text',
                'default' => ''
            ],
            'title_3' => [
                'label' => 'Título 3',
                'type' => 'text',
                'default' => 'Terceiro destaque'
            ],
            'description_3' => [
                'label' => 'Descrição 3',
                'type' => 'textarea',
                'default' => 'Edite o texto para complementar a galeria.'
            ]
        ]
    ],

    'cta_whatsapp' => [
        'label' => 'Botão WhatsApp',
        'category' => 'cta',

        'fields' => [

            'phone' => [
                'label' => 'Telefone (com DDD)',
                'type' => 'text'
            ],

            'text' => [
                'label' => 'Texto do botão',
                'type' => 'text',
                'default' => 'Falar no WhatsApp'
            ],

            'message' => [
                'label' => 'Mensagem padrão',
                'type' => 'textarea',
                'default' => 'Olá, quero saber mais'
            ],

            'align' => [
                'label' => 'Alinhamento',
                'type' => 'select',
                'options' => [
                    'left' => 'Esquerda',
                    'center' => 'Centro',
                    'right' => 'Direita'
                ],
                'default' => 'center'
            ]

        ]
    ],

    'lead_form' => [
        'label' => 'Formulário',
        'category' => 'cta',

        'fields' => [

            'title' => [
                'label' => 'Título',
                'type' => 'text',
                'default' => 'Comece pelo e-mail'
            ],

            'description' => [
                'label' => 'Descrição',
                'type' => 'textarea',
                'default' => 'Informe seu e-mail para receber o link de continuação.'
            ],

            'base_id' => [
                'label' => 'Produto/Base divulgada',
                'type' => 'select',
                'options' => [
                    '' => 'Nenhuma'
                ],
                'default' => ''
            ],

            'external_url' => [
                'label' => 'URL externa apos cadastro',
                'type' => 'text',
                'default' => ''
            ],

            'align' => [
                'label' => 'Alinhamento',
                'type' => 'select',
                'options' => [
                    'left' => 'Esquerda',
                    'center' => 'Centro',
                    'right' => 'Direita'
                ],
                'default' => 'center'
            ]

        ]
    ],

    'testimonials' => [
        'label' => 'Depoimentos',
        'category' => 'content',

        'fields' => [

            'title' => [
                'label' => 'Título',
                'type' => 'text',
                'default' => 'Depoimentos'
            ],

            'name_1' => [
                'label' => 'Nome 1',
                'type' => 'text',
                'default' => 'Nome do cliente'
            ],
            'role_1' => [
                'label' => 'Identificação 1',
                'type' => 'text',
                'default' => 'Cliente'
            ],
            'text_1' => [
                'label' => 'Depoimento 1',
                'type' => 'textarea',
                'default' => 'Use este espaço para um depoimento curto e direto.'
            ],
            'image_1' => [
                'label' => 'Imagem 1 (URL da Biblioteca)',
                'type' => 'text',
                'default' => ''
            ],

            'name_2' => [
                'label' => 'Nome 2',
                'type' => 'text',
                'default' => 'Nome do cliente'
            ],
            'role_2' => [
                'label' => 'Identificação 2',
                'type' => 'text',
                'default' => 'Cliente'
            ],
            'text_2' => [
                'label' => 'Depoimento 2',
                'type' => 'textarea',
                'default' => 'Conte aqui uma percepção, resultado ou experiência positiva.'
            ],
            'image_2' => [
                'label' => 'Imagem 2 (URL da Biblioteca)',
                'type' => 'text',
                'default' => ''
            ],

            'name_3' => [
                'label' => 'Nome 3',
                'type' => 'text',
                'default' => 'Nome do cliente'
            ],
            'role_3' => [
                'label' => 'Identificação 3',
                'type' => 'text',
                'default' => 'Cliente'
            ],
            'text_3' => [
                'label' => 'Depoimento 3',
                'type' => 'textarea',
                'default' => 'Finalize com um terceiro depoimento simples para reforçar confiança.'
            ],
            'image_3' => [
                'label' => 'Imagem 3 (URL da Biblioteca)',
                'type' => 'text',
                'default' => ''
            ]
        ]
    ],

    'cta_button' => [
        'label' => 'Botão',
        'category' => 'cta',

        'fields' => [

            'text' => [
                'label' => 'Texto',
                'type' => 'text',
                'default' => 'Acessar'
            ],

            'link' => [
                'label' => 'Link',
                'type' => 'text',
                'default' => '#'
            ],

            'target' => [
                'label' => 'Abrir em',
                'type' => 'select',
                'options' => [
                    '_self' => 'Mesma aba',
                    '_blank' => 'Nova aba'
                ],
                'default' => '_self'
            ],

            'align' => [
                'label' => 'Alinhamento',
                'type' => 'select',
                'options' => [
                    'left' => 'Esquerda',
                    'center' => 'Centro',
                    'right' => 'Direita'
                ],
                'default' => 'center'
            ],

            'style' => [
                'label' => 'Cor do botão',
                'type' => 'select',
                'options' => [
                    'primary' => 'Primary',
                    'secondary' => 'Secondary',
                    'green' => 'Verde',
                    'red' => 'Vermelho',
                    'outline' => 'Outline'
                ],
                'default' => 'primary'
            ]

        ]
    ],
    /* ================= BLOG ================= */

    'blog_header' => [
        'label' => 'Blog Header',
        'category' => 'blog',

        'fields' => [

            'title' => [
                'label' => 'Título',
                'type' => 'text'
            ],

            'subtitle' => [
                'label' => 'Subtítulo',
                'type' => 'textarea'
            ],

            'author' => [
                'label' => 'Autor',
                'type' => 'text'
            ],

            'date' => [
                'label' => 'Data',
                'type' => 'text'
            ]

        ]
    ],

    'blog_content' => [
        'label' => 'Blog Conteúdo',
        'category' => 'blog',

        'fields' => [

            'title' => [
                'label' => 'Título',
                'type' => 'text'
            ],

            'content' => [
                'label' => 'Conteúdo (HTML)',
                'type' => 'textarea'
            ],

            'media_type' => [
                'label' => 'Tipo de mídia',
                'type' => 'select',
                'options' => [
                    'image' => 'Imagem',
                    'video' => 'Vídeo (iframe)'
                ],
                'default' => 'image'
            ],

            'image' => [
                'label' => 'URL da mídia',
                'type' => 'text'
            ],

            'link' => [
                'label' => 'Link do botão',
                'type' => 'text'
            ]

        ]
    ],


    'blog_text' => [
        'label' => 'Texto',
        'category' => 'blog',

        'fields' => [

            'content' => [
                'label' => 'Conteúdo (HTML)',
                'type' => 'textarea'
            ]

        ]
    ],

    'blog_image' => [
        'label' => 'Imagem',
        'category' => 'blog',

        'fields' => [

            'image' => [
                'label' => 'URL da imagem',
                'type' => 'text'
            ],

            'caption' => [
                'label' => 'Legenda',
                'type' => 'text'
            ]

        ]
    ],

    'blog_quote' => [
        'label' => 'Citação',
        'category' => 'blog',

        'fields' => [

            'text' => [
                'label' => 'Texto',
                'type' => 'textarea'
            ],

            'author' => [
                'label' => 'Autor',
                'type' => 'text'
            ]

        ]
    ],

    'blog_cta' => [
        'label' => 'Blog CTA',
        'category' => 'blog',

        'fields' => [

            'text' => [
                'label' => 'Texto alternativo',
                'type' => 'text'
            ],

            'button_text' => [
                'label' => 'Texto do botão',
                'type' => 'text'
            ],

            'link' => [
                'label' => 'Link',
                'type' => 'text',
                'default' => '#'
            ],

            'align' => [
                'label' => 'Alinhamento',
                'type' => 'select',
                'options' => [
                    'left' => 'Esquerda',
                    'center' => 'Centro',
                    'right' => 'Direita'
                ],
                'default' => 'center'
            ]

        ]
    ],

    'blog_video' => [
        'label' => 'Vídeo',
        'category' => 'blog',

        'fields' => [

            'url' => [
                'label' => 'URL do vídeo (YouTube/Vimeo)',
                'type' => 'text'
            ],

            'caption' => [
                'label' => 'Legenda',
                'type' => 'text'
            ]

        ]
    ],

    'blog_list' => [
        'label' => 'Lista de Posts',
        'category' => 'blog',

        'fields' => [
            // não precisa campos por enquanto (usa globalData)
        ]
    ],
];
