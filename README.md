# Mateus Pet Sitter — V9 (GitHub Pages + servidor cPanel)

## Correção importante
O GitHub Pages é apenas a **entrada visual** do aplicativo. Os botões abrem telas auxiliares locais e estas oferecem acesso ao site PHP no cPanel. Assim, uma queda no domínio do cPanel não faz a **página do GitHub** desaparecer. A opção **Verificar novamente** testa a resposta da rede, mas não garante que o MySQL ou o login estejam corretos.

As funções reais (contas, pets, senhas, agenda em tempo real, disponibilidade, clientes estrela, recorrência e aprovação) continuam nos arquivos PHP/JS e no banco MySQL do cPanel.

## Para colocar no GitHub
1. Extraia o ZIP GitHub na pasta clonada `Pataagenda` (nunca apague `.git`).
2. Mantenha `index.html`, `cliente.html`, `admin.html`, `404.html` na **raiz** da branch `main`.
3. No GitHub Desktop: **Commit to main → Push origin**.
4. Em **Settings → Pages**, use **Deploy from branch → main → /(root)**.
5. Acesse `https://mnsistemasdesenvolvimento.github.io/Pataagenda/` (com P maiúsculo no caminho).
6. Se houver erros antigos, recarregue com **Ctrl+Shift+R** e confira `Actions`/`Pages` para falha de publicação.

## Para atualizar o cPanel (se quiser as correções da V9)
1. Faça backup da pasta `/public_html/matheus-pet-care` e do banco `marce7856_petcare`.
2. Use o ZIP **V9_SERVIDOR_CPANEL**; extraia em `/public_html/matheus-pet-care`.
3. NÃO altere nem apague `_private/config.php`. A conexão funcional continua nesse arquivo.
4. Não há migração SQL obrigatória nesta atualização. Não importe `instalar_mysql.sql` novamente.
5. Abra diretamente `/matheus-pet-care/index.php`, `/cliente.php` e `/admin.php` para testar.

## Por que não basta atualizar o GitHub?
O GitHub Pages **não executa PHP** e não conecta diretamente ao MySQL hospedado no cPanel. A atualização no GitHub NÃO envia os arquivos PHP para o cPanel. São dois destinos de publicação distintos.

## Segurança
- NUNCA publique `/_private/config.php` ou senhas no GitHub.
- `config.example.php` é um modelo SEM senha.
- Mesmo com `.gitignore`, um segredo que já foi versionado antes deve ser removido do histórico, e a senha trocada.
- A senha do MySQL que apareceu no chat deve ser substituída por outra no cPanel e atualizada APENAS no servidor.

## V9 — alterações
- Nome consistente: **Mateus Pet Sitter**.
- GitHub: novas páginas de entrada separadas para cliente e administrador, sem redirecionamentos automáticos.
- O usuário vê uma mensagem informativa quando a hospedagem não responde, em vez de uma tela sem explicação.
- Navegação mobile, indicação de clique e etapas de cadastro mantidas.
- cPanel: timeout nas chamadas de API e mensagem clara de rota desatualizada.
- cPanel: calendário público com datas bloqueadas quando a API não responde, evitando apresentar horários como livres sem confirmação.
- API, cadastro, painel administrativo, sessões, logout, cookies "lembrar acesso" e agenda V8 preservados.

## Limites dos testes
Arquivos e interface podem ser validados localmente. O banco MySQL real e o status do domínio da hospedagem precisam ser testados pelo responsável no cPanel. Nenhum cadastro real foi alterado.
