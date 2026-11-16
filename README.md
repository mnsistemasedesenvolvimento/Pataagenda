# Mateus Pet Sitter V8 — projeto completo para GitHub + cPanel

Este ZIP contém **todos os arquivos atuais da aplicação V8** (PHP, API, CSS e JavaScript), o SQL de instalação/migração e uma página inicial `index.html` pronta para **GitHub Pages**. Não contém dados reais de clientes nem senhas.

## Muito importante: onde o site realmente funciona

- **GitHub Pages** exibe o `index.html` (dois cartões: Quero agendar e Administrador). Ao clicar, a pessoa vai para os formulários PHP no cPanel.
- **cPanel / Integrator** executa `index.php`, `cliente.php`, `admin.php` e `api/index.php` e se conecta ao MySQL.
- Enviar arquivos ao GitHub **não atualiza automaticamente o PHP do cPanel**. Para colocar uma atualização da aplicação no ar, copie os arquivos PHP/CSS/JS ao cPanel também (ou configure implantação automatizada em outra etapa).

## Atualizar o repositório pelo GitHub Desktop

1. Extraia o ZIP, abra a pasta extraída e copie seus arquivos **para dentro da pasta local clonada do repositório** `Pataagenda`. Não envie o ZIP inteiro como um único arquivo.
2. **Não apague a pasta oculta `.git`**. No GitHub Desktop, revise os arquivos alterados. O `index.html` (letra `i` minúscula) deve ficar na raiz.
3. Remova arquivos antigos de versões não usadas (`Index.html` maiúsculo, `cliente.html`, `admin.html`, `config.js` da versão Supabase), caso ainda estejam no GitHub e não sejam usados por outros projetos. Faça backup antes.
4. Confirme que **`_private/config.php` NÃO está entre os arquivos que serão enviados ao GitHub**. Se já estiver versionado, a regra `.gitignore` por si só não o remove; é preciso remover o arquivo do GitHub sem remover o do cPanel e trocar a senha exposta.
5. No GitHub Desktop, escreva um resumo, clique **Commit to main**, depois **Push origin**.
6. No repositório, em **Settings → Pages**, deixe **Deploy from a branch**, `main`, `/(root)`.
7. Acesse `https://mnsistemasdesenvolvimento.github.io/Pataagenda/` para ver a página com os dois cartões.

## Atualizar o site real no cPanel

- Antes de atualizar, faça cópia dos arquivos atuais e exporte o banco `marce7856_petcare`.
- O aplicativo fica em `public_html/matheus-pet-care/`.
- Copie as páginas `.php`, scripts `.js`, `site.css`, `.htaccess`, e a pasta `api/` do mesmo pacote para a pasta do aplicativo no cPanel. O `index.html` não é necessário para o funcionamento no cPanel: o `.htaccess` define `DirectoryIndex index.php`.
- **Nunca apague nem substitua o `_private/config.php` que já conecta ao MySQL no cPanel.** O arquivo `config.example.php` é apenas exemplo.
- O script `instalacao/instalar_mysql.sql` é só para uma instalação nova. `instalacao/atualizar_v7.sql` é para uma atualização de banco que ainda não tenha sido feita. **Não importe SQL novamente se já instalou a V7.**
- Não é necessário apagar contas, clientes, pets ou visitas.

## Segurança

- **Nunca guarde senhas no repositório público** nem em um ZIP que irá para o GitHub. Credenciais reais devem ficar apenas no `_private/config.php` do servidor.
- O arquivo `.gitignore` foi incluído para evitar inclusão acidental do `config.php`. Ele não substitui a revisão do que já foi versionado.
- A senha do banco foi compartilhada em conversa anteriormente; por segurança, troque-a no cPanel e atualize somente o `config.php` do servidor.
- O GitHub Pages **não** fornece login PHP/MySQL por conta própria; os links encaminham para a hospedagem configurada.

## Arquivos principais

- `index.html`: página de entrada no GitHub Pages.
- `index.php`, `cliente.php`, `admin.php`, `privacidade.php`: páginas reais do site no cPanel.
- `api/index.php`: API PHP com o login, cadastros, disponibilidade e solicitações.
- `site.js`, `site.css`, `cliente.js`, `admin.js`: estilos e comportamento.
- `_private/config.example.php`: modelo **sem senha real**.
- `instalacao/*.sql`: esquema e migração, para uso apenas quando necessário.

O endereço público do GitHub e o endereço do aplicativo cPanel são diferentes. O uso do banco depende do domínio do cPanel estar acessível.
