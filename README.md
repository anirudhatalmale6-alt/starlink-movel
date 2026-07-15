# Starlink Móvel — Landing Page + Captação de Leads

Clone completo do site, com sistema de quiz de qualificação, geração automática de
mensagem de WhatsApp e painel administrativo com fila de atendentes (round-robin).

## O que está incluído

- **Landing page** idêntica ao original (design, animações, contadores, responsivo).
- **Quiz de qualificação** de 8 passos (abre nos botões "Iniciar Ativação" / "Falar com Suporte").
- **Geração automática** da mensagem de WhatsApp com os dados do lead.
- **Fila de atendentes (round-robin):** cada lead é enviado para o próximo número da fila,
  em sequência (1 → 2 → 3 → 1 …).
- **Painel administrativo** (`/admin.php`): cadastra/edita/reordena os números de WhatsApp,
  ativa/pausa atendentes e visualiza todos os leads capturados.

## Requisitos

- PHP 7.4+ com as extensões `pdo_sqlite` (padrão na maioria das hospedagens).
- Nenhum banco de dados externo é necessário — usa SQLite (arquivo em `/data`).

## Instalação (hospedagem compartilhada / cPanel / Hostinger)

1. Envie a pasta `site/` para a raiz pública (`public_html`).
2. Garanta que a pasta `data/` tenha permissão de escrita (755 ou 775).
3. Acesse o site normalmente pelo navegador.
4. Acesse o painel em `https://seudominio.com/admin.php`.

## Configuração

Edite `api/config.php`:

- `ADMIN_PASSWORD` — **troque** pela sua senha do painel (padrão: `starlink2025`).
- `WHATSAPP_TEMPLATE` — modelo da mensagem gerada (opcional).

## Como usar

1. Entre no painel `/admin.php` com sua senha.
2. Na aba **Atendentes (Fila)**, adicione os números de WhatsApp de atendimento.
3. Pronto: cada visitante que concluir o quiz é direcionado ao próximo número da fila,
   já com a mensagem personalizada montada.
4. Na aba **Leads**, acompanhe todos os contatos capturados.

## Estrutura

```
site/
├── index.html          # landing page
├── admin.php           # painel administrativo
├── css/style.css
├── js/app.js           # animações + quiz
├── img/                # imagens
├── api/
│   ├── config.php      # configurações (senha, modelo de mensagem)
│   ├── db.php          # banco SQLite + fila round-robin
│   └── lead.php        # recebe o lead e devolve o link do WhatsApp
└── data/               # banco de dados (criado automaticamente)
```
