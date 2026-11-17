# Mateus Pet Sitter V10.1 — Correção dos dois acessos

## Causa e correção
- V10 guardava o token da área do Cliente e do Administrador na mesma chave do navegador. Abrir uma segunda aba podia reaproveitar a conta incorreta.
- A sessão PHP do cPanel também era única. Agora o servidor usa cookies separados por área, e valida o papel da conta no MySQL.
- A tela antiga que mostra **Matheus Pet Care** e **Recurso não encontrado** indica que uma API anterior está ativa no cPanel. A V10.1 identifica isso pelo campo `api_version` e mostra instruções, não um erro genérico.

## Instale nesta ordem (precisa das DUAS atualizações)
1. Faça backup da pasta `/public_html/matheus-pet-care/` e exporte o banco `marce7856_petcare` no phpMyAdmin.
2. **Servidor:** envie o ZIP `Mateus_Pet_Sitter_V10_1_CPANEL.zip` para `/public_html/matheus-pet-care/` e extraia ali. Confirme que o arquivo **`api/index.php`** foi substituído. **Não apague o banco; não substitua `_private/config.php`.**
3. Se ainda não tiver feito a instalação da V10, execute uma única vez o SQL **`instalacao/atualizar_v10.sql`**. Ele cria `pet_external_tokens` sem apagar dados. Quem já fez a V10 não precisa rodar de novo.
4. Confirme a API no navegador: `https://marce7856.c33.integrator.host/matheus-pet-care/api/index.php?action=health`. Deve aparecer `"ok":true`, `"database":"connected"` e `"api_version":"10.1"`.
5. **GitHub:** extraia o ZIP `Mateus_Pet_Sitter_V10_1_GITHUB.zip` no repositório local `Pataagenda` pelo GitHub Desktop. **Não envie o ZIP do cPanel ao GitHub**; nele há PHP que o GitHub não executa. Confirme `index.html`, `cliente.html`, `admin.html`, `site.js`, `api-config.js` na raiz. Clique em Commit to main → Push origin.
6. Acesse `https://mnsistemasedesenvolvimento.github.io/Pataagenda/` e pressione Ctrl+Shift+R se necessário.

## Teste real em duas abas
- Aba 1: `https://mnsistemasedesenvolvimento.github.io/Pataagenda/admin.html` — login do Mateus.
- Aba 2: `https://mnsistemasedesenvolvimento.github.io/Pataagenda/cliente.html` — cadastro/login com OUTRO e-mail do tipo cliente.
- Cliente cadastra pet, seleciona data/horário livre e solicita uma visita.
- Mateus abre Pedidos e confirma. Cliente abre Meus pedidos e vê a confirmação.
- Se a agenda parecer vazia, use Atualizar agenda e confira resposta da API. Sem banco online não se pode mostrar disponibilidade real.
- Sair do painel Mateus encerra apenas a sessão de administrador; cliente permanece conectado na outra aba. Sair do cliente não desconecta o administrador.

## Segurança
- O ZIP não contém a senha do banco. Mantenha `_private/config.php` exclusivamente no cPanel, protegido por `.htaccess`.
- Sua senha MySQL já foi compartilhada na conversa; redefina-a no cPanel quando possível.
- Nunca copie `_private/config.php` para o GitHub público.
- As mudanças são de sessão e interface. **Não é necessário apagar ou recriar tabelas.**

## Diagnóstico de versão
`.../api/index.php?action=health` deve incluir `api_version: 10.1`. Se não incluir, o PHP antigo ainda está sendo executado no cPanel: confira o caminho de extração e o cache.
`.../api/index.php?action=bootstrap&area=client` retorna `api_version: 10.1` e `area: client`.
`.../api/index.php?action=bootstrap&area=admin` retorna `api_version: 10.1` e `area: admin`.
