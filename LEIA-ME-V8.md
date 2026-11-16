# Mateus Pet Sitter V8 — atualização segura para cPanel / MySQL

## O que mudou

- Nome **Mateus Pet Sitter** em todas as páginas e títulos. A pasta técnica `/matheus-pet-care/` e o banco não mudam.
- Ao abrir **Quero agendar** com sessão administrativa ativa, o visitante **não é redirecionado**. A agenda pública continua visível e aparece um botão para encerrar a sessão administrativa antes de usar conta de cliente.
- Os links **Início** do painel administrativo e o botão **Sair do painel** agora executam logout no servidor e revogam o cookie de 'lembrar login' antes de voltar ao início. Fechar a aba sem clicar Sair pode preservar a sessão, caso a opção lembrar login esteja ativa.
- Área de login do cliente rejeita credenciais administrativas, e vice-versa, sem trocar silenciosamente a sessão.
- Agenda administrativa desenha o calendário de datas logo de início. Se a conexão à API falhar, mostra motivo, mantém as datas e oferece **Atualizar agenda**, sem inventar horários livres ou executar bloqueios offline.
- Botões **Ir para hoje**, **Atualizar agenda**, liberar/bloquear dia e horários isolados. Pedidos existentes são preservados.
- Versão dos recursos atualizada para `8.0.0` para evitar CSS/JS antigos em cache.

## Instalação

1. **Faça backup da pasta e exporte o banco atual** antes de instalar.
2. Entre em **cPanel → Gerenciador de arquivos → `public_html/matheus-pet-care/`**.
3. Envie o ZIP e clique **Extrair** nessa pasta. Confirme a substituição dos arquivos do site.
4. **Não exclua e não substitua** `_private/config.php`. Este pacote NÃO contém suas credenciais. Também não contém SQL de migração: **não é preciso importar ou apagar tabelas**.
5. Abra **`https://marce7856.c33.integrator.host/matheus-pet-care/`** (não a versão antiga do GitHub Pages). Faça Ctrl+Shift+R.
6. Confira o banco em `/api/index.php?action=health`, que deve responder `{ "ok": true, "database": "connected" }`.

## Teste essencial depois da atualização

1. Administrador: entre em `admin.php`; a aba **Agenda** deve mostrar mês, dias e horários. Toque em **Ir para hoje** e **Atualizar agenda**; bloqueie um horário livre e depois libere-o.
2. Toque **Sair e ir ao início**, depois **Quero agendar**; não pode redirecionar ao administrador.
3. Com o administrador conectado, abra manualmente `cliente.php`: deve aparecer um aviso com botão **Sair do administrador e continuar**, sem redirecionamento automático.
4. Cliente: entre com conta de cliente e valide cadastro de pet, solicitação e acompanhamento. Não é necessário criar clientes fictícios.
5. Se agenda mostrar erro, anote a mensagem (sem senhas) e teste `api/index.php?action=availability&month=AAAA-MM` com o mês atual, para verificar a resposta do MySQL.

## Regras de segurança

- **Não publique** `_private/config.php` ou um ZIP contendo senha no GitHub público.
- O logout revoga o login persistente **deste navegador**. Tokens lembrados em outros dispositivos permanecem até que sejam revogados.
- O bloqueio de horários opera no servidor, com validação de permissão. Nenhum bloqueio será executado sem conexão.
- O diretório `/matheus-pet-care/` é preservado para manter os endereços existentes. GitHub Desktop, sozinho, não atualiza o PHP no cPanel.
