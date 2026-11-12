# Lux ERP

## Configuração

O PHP lê as credenciais do banco destas variáveis de ambiente:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Elas precisam estar definidas no processo PHP (PHP-FPM/Apache), não apenas no terminal usado para implantação. Como alternativa para hospedagens sem variáveis de ambiente para PHP, crie `lux-erp-config.php` um nível acima da pasta do projeto/publicação, fora da raiz pública, retornando um array associativo com as chaves `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS`. Restrinja as permissões de leitura desse arquivo à conta do site. Também é possível apontar `LUX_CONFIG_FILE` para um caminho privado diferente. Variáveis de ambiente têm precedência. Sem uma configuração completa, páginas que acessam o banco retornam erro interno e registram o motivo no log do servidor.

Como credenciais já estiveram armazenadas no código do projeto, troque a senha do usuário do banco e atualize o ambiente de produção. Remover o valor do arquivo atual não o remove do histórico do Git.

O arquivo `u121600320_lux.sql` é um dump MariaDB 11.8 e contém dados de exemplo. Importe-o somente em um banco novo ou após backup e conferência do destino; não o execute sobre uma base com dados que devam ser preservados. O dump inclui tabelas legadas em inglês e as tabelas portuguesas usadas pelo fluxo atual. Não remova essas tabelas sem confirmar dependências e dados existentes.

Para gerar um hash de senha master, execute `php gerar-hash.php` em um terminal e informe a senha pela entrada padrão. O script não funciona via web.

## Implantação

- Use HTTPS e configure as variáveis de banco no ambiente do PHP.
- Restrinja permissões do usuário do banco ao schema necessário e mantenha backups testados.
- Use uma versão de PHP suportada pela hospedagem e habilite PDO MySQL.
- O dump usa collation `utf8mb4_uca1400_ai_ci`; confirme compatibilidade da versão MariaDB de produção antes de importá-lo.
- As telas carregam Tailwind via CDN; a interface depende de acesso externo ou de uma versão local do CSS.
- Configure o servidor web para não executar scripts dentro das pastas de uploads e mantenha TLS ativo.
