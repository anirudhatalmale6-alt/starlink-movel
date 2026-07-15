<?php
/* ============================================================
   Configurações gerais
   ============================================================ */

// Senha do painel administrativo. TROQUE por uma senha forte.
define('ADMIN_PASSWORD', 'starlink2025');

// Caminho do banco de dados SQLite (fora da raiz pública seria o ideal;
// aqui fica em /data com proteção via .htaccess).
define('DB_PATH', __DIR__ . '/../data/starlink.sqlite');

// Modelo da mensagem de WhatsApp gerada para cada lead.
// Placeholders disponíveis: {{nome}} {{localizacao}} {{motivo_uso}}
// {{internet_atual}} {{dispositivo}} {{ativacao}}
define('WHATSAPP_TEMPLATE',
"Olá, meu nome é {{nome}}. Finalizei meu processo de qualificação na Starlink Móvel.\n\n" .
"📍 Localização: {{localizacao}}\n" .
"🎯 Motivo de uso: {{motivo_uso}}\n" .
"📡 Internet atual: {{internet_atual}}\n" .
"📱 Dispositivo: {{dispositivo}}\n" .
"⚡ Ativação: Imediata\n\n" .
"Gostaria de continuar meu atendimento para realizar minha instalação.");
