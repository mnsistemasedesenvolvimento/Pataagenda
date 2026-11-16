# Mateus Pet Super — V7 (cPanel + MySQL)

## O que mudou

- Nome das páginas e textos: **Mateus Pet Super**. O endereço técnico da pasta **matheus-pet-care** e o banco existente não mudam.
- Cadastro de clientes revisado: validação de senha a partir de 8 caracteres, limite de inscrições separado do limite de logins e mensagens de erro junto ao próprio formulário.
- Login do cliente e administrador: opção **Lembrar login por 30 dias**. O sistema utiliza cookie HttpOnly com um token aleatório cujo hash fica no MySQL; **não salva a senha no navegador**. O cookie só é ativado em **HTTPS**.
- Agenda administrativa abre primeiro, com datas do mês, horários individuais e botões para liberar/bloquear **todos os horários livres** de um dia. Solicitações e confirmações existentes não são excluídas.
- Permanecem: clientes estrela, recorrência mensal, endereço, observações, pets, solicitações, aprovação/recusa, bloqueios e calendário público.

## Atualizar SEM perder o banco, a senha ou os cadastros

**Faça backup antes:** exporte o banco `marce7856_petcare` no phpMyAdmin e guarde uma cópia de `public_html/matheus-pet-care`.

1. No phpMyAdmin, selecione **somente** o banco `marce7856_petcare`.
2. Importe o arquivo **`instalacao/atualizar_v7.sql`** deste ZIP. Esse script cria apenas a tabela `pet_remember_tokens` se ela ainda não existir, sem apagar ou modificar os cadastros existentes. O recurso de lembrar login depende dessa tabela.
3. No Gerenciador de Arquivos do cPanel, vá para **`public_html/matheus-pet-care`**.
4. Envie o ZIP, clique em **Extrair** nessa pasta, confirme a substituição dos arquivos com nomes iguais.
5. **Não apague nem sobrescreva** o arquivo `public_html/matheus-pet-care/_private/config.php`: este ZIP não contém esse arquivo nem sua senha. Inclui somente `config.example.php` como referência.
6. Abra **`https://marce7856.c33.integrator.host/matheus-pet-care/`** e faça uma recarga completa (Ctrl+Shift+R).
7. Verifique o status do banco em `/matheus-pet-care/api/index.php?action=health` (deve retornar `ok:true`).

## Testes recomendados em dois aparelhos/navegadores

**Cliente novo:** abra `cliente.php`, escolha "Criar cadastro" e use um e-mail **diferente** do login do administrador. Cadastre um pet e solicite um dia/horário livre. Veja o pedido em "Meus pedidos".

**Mateus:** entre por `admin.php` com a senha da conta administrativa existente. A aba Agenda será aberta. Selecione uma data para bloquear um horário, bloquear os livres do dia ou liberar os bloqueados; veja a disponibilidade refletida em `cliente.php`. Confira e confirme o pedido em "Pedidos".

**Lembrar login:** marque a caixa no formulário de cliente ou de administrador. Após entrar, feche a aba, abra novamente no **mesmo navegador e aparelho** e confira que a sessão foi restaurada. "Sair" revoga o token desse dispositivo. Não marque essa opção em aparelhos públicos/compartilhados.

## Se o cadastro ou login ainda falhar

A mensagem agora aparece no formulário, próxima do botão. Copie somente a mensagem (sem senha) para investigação:

- "E-mail ou senha incorretos": confirme credenciais, ou escolha "Criar cadastro" se for um novo cliente.
- "Este e-mail já está cadastrado": use "Já sou cliente"; a conta de administrador não é um novo cadastro de cliente.
- "Sessão expirada": permita cookies, confira HTTPS e faça Ctrl+Shift+R; tente uma aba normal (não anônima).
- "Muitas tentativas": espere o limite de segurança expirar; não repita tentativas incessantes.
- Mensagem de banco indisponível: teste a API de saúde e confira a configuração que já funciona no cPanel.

## Segurança / limitações

Este pacote **não contém senhas**, arquivos de demonstração, clientes fictícios ou imagens. Não publique o `config.php` de produção no GitHub.

Se o cliente esquecer a senha e não tiver sessão ativa, a recuperação automática por e-mail **ainda não está implementada**. Não use comandos SQL que guardem senhas em texto puro. O recurso "Lembrar login" é válido somente após importar o SQL de atualização e utilizar HTTPS.

Não confunda **GitHub Pages** (site estático sem PHP) com **cPanel** (local de publicação deste aplicativo PHP/MySQL). Atualizações enviadas ao GitHub Desktop não modificam automaticamente os arquivos do cPanel.
