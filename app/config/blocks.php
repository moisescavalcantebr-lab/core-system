<?php
declare(strict_types=1);

/* =========================
FUNÃ‡ÃƒO (PROTEGIDA)
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
            'label' => 'Texto do botÃ£o',
            'default' => 'ComeÃ§ar agora'
        ],
    ]
],

'benefits_cards' => [
    'label' => 'Beneficios',
    'fields' => [

        'title' => [
            'type' => 'text',
            'label' => 'Titulo da seÃ§Ã£o',
            'default' => 'Por que escolher nossa soluÃ§Ã£o?'
        ],

        'cards' => [
            'type' => 'group',
            'label' => 'Cards',
            'fields' => [

                'icon' => [
                    'type' => 'select',
                    'label' => 'icone',
                    'options' => [
                        'star' => 'â­ Star',
                        'check' => 'âœ” Check',
                        'rocket' => 'ðŸš€ Rocket',
                        'shield' => 'ðŸ›¡ Shield',
                        'bolt' => 'âš¡ Bolt',
                        'heart' => 'â¤ï¸ Heart',
                        'users' => 'ðŸ‘¥ Users',
                        'chart' => 'ðŸ“Š Chart'
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
                    'text' => 'Layout pensado para gerar confianÃ§a e aÃ§Ã£o.'
                ],
                [
                    'icon' => 'check',
                    'title' => 'Contato imediato',
                    'text' => 'WhatsApp e formulÃ¡rio integrados.'
                ],
                [
                    'icon' => 'rocket',
                    'title' => 'Totalmente configurÃ¡vel',
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
            'type' => 'text'
        ],

        'items' => [
            'label' => 'Produtos',
            'type' => 'group',

            'fields' => [

                'name' => [
                    'label' => 'Nome',
                    'type' => 'text'
                ],

                'description' => [
                    'label' => 'Descrição',
                    'type' => 'textarea'
                ],

                'image' => [
                    'label' => 'Imagem (URL)',
                    'type' => 'text'
                ]

            ]
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
            'default' => 'Entre em contato'
        ],

        'description' => [
            'label' => 'Descrição',
            'type' => 'textarea',
            'default' => 'Preencha o formulário e retornaremos rapidamente.'
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
            'type' => 'text'
        ],

        'items' => [
            'label' => 'Depoimentos',
            'type' => 'group',

            'fields' => [

                'name' => [
                    'label' => 'Nome',
                    'type' => 'text'
                ],

                'role' => [
                    'label' => 'Cargo',
                    'type' => 'text'
                ],

                'text' => [
                    'label' => 'Depoimento',
                    'type' => 'textarea'
                ],

                'image' => [
                    'label' => 'Imagem (URL)',
                    'type' => 'text'
                ]

            ]
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