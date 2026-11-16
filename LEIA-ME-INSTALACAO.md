# Matheus Pet Care V6 — Atualização completa com MySQL configurado

## O que foi incluído

Este pacote contém todos os arquivos do site V6, incluindo `_private/config.php` já preenchido para o banco `marce7856_petcare` e o usuário `marce7856_petapp`. O host configurado é `127.0.0.1`.

**ATENÇÃO DE SEGURANÇA:** o ZIP contém uma senha real. Não publique o ZIP nem `_private/config.php` no GitHub, GitHub Pages ou repositórios públicos, nem compartilhe com terceiros. A pasta `_private` inclui `.htaccess` com acesso externo negado. A senha já foi divulgada na conversa: troque-a no cPanel assim que possível e atualize o arquivo no servidor. Depois da troca, este ZIP antigo não poderá ser reutilizado sem atualizar a senha.

## Atualizar no cPanel sem apagar dados

1. Faça uma cópia de segurança do diretório `public_html/matheus-pet-care` e exporte o banco `marce7856_petcare` no phpMyAdmin.
2. No **Gerenciador de arquivos do cPanel**, abra `public_html/matheus-pet-care`.
3. Carregue este ZIP **nesta pasta** e clique em **Extrair**. Os arquivos dentro do ZIP estão diretamente na raiz, com subpastas `api/`, `_private/` e `instalacao/`.
4. Confirme a substituição dos arquivos com o mesmo nome, incluindo `_private/config.php` (já configurado) e `_private/.htaccess`.
5. O banco e as tabelas existentes não são apagados por esse procedimento. **Não execute o SQL de instalação novamente se as seis tabelas já existem.**
6. Confira o teste `https://SEU-DOMINIO/matheus-pet-care/api/index.php?action=health` e abra a página inicial `/matheus-pet-care/`.
7. Teste o login do Matheus e os calendários de cliente e administrador. Se não aparecerem atualizações, recarregue a página com Ctrl+Shift+R.

## Estrutura

- `index.php` — entrada com cliente e administrador
- `cliente.php`, `cliente.js` — cadastro, pets, calendário e solicitações
- `admin.php`, `admin.js` — painel privado do Matheus
- `site.css`, `site.js` — estilos e funções comuns
- `api/index.php` — API PHP conectada ao MySQL
- `_private/config.php` — credenciais MySQL pré-configuradas, **não publicar no GitHub**
- `_private/.htaccess` — impede acesso direto aos arquivos privados via Apache
- `instalacao/instalar_mysql.sql` — somente para instalações novas; não apaga dados

## Limitações e cuidados

- Atualização do calendário é por consulta a cada 15 segundos, não por push WebSocket instantâneo.
- Horários podem mudar entre atualizações; a reserva no banco impede dupla ocupação do mesmo horário.
- Não existe envio automático de WhatsApp nem recuperação de senha por e-mail nesta versão.
- Para novas versões com senha alterada, preserve o `_private/config.php` atualizado no servidor ou gere um pacote com a nova configuração; não use uma senha antiga.
