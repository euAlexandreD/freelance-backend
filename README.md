# Freelance — API

API da plataforma **Freelance**, que conecta clientes a prestadores de serviço. O prestador publica seus serviços com preço e fotos, o cliente solicita, o prestador aceita e conclui, e o cliente avalia.

O frontend (React) fica no repositório [freelance-frontend](https://github.com/euAlexandreD/freelance-frontend).

## Funcionalidades

- **Contas**: cadastro, login e logout com token ([Laravel Sanctum](https://laravel.com/docs/sanctum)).
- **Perfis**: cliente e prestador, com foto, cidade/UF, telefone, título, bio e link de portfólio.
- **Serviços**: título, categoria, descrição, preço (fixo, a partir de, por hora ou a combinar) e até 5 fotos. Podem ser pausados.
- **Pedidos**: o cliente solicita, e o pedido passa pelos status `pendente → aceito → concluído`. Também pode terminar como `recusado` ou `cancelado`.
- **Conversa no pedido**: cliente e prestador trocam mensagens dentro do pedido, com contagem de não lidas.
- **E-mails automáticos**: pedido recebido, aceito, recusado, concluído (com convite para avaliar) e cancelado, além de aviso de mensagem nova. O aviso de mensagem é agrupado: no máximo um a cada 15 minutos por conversa, e só se a mensagem continuar sem leitura depois de 2 minutos.
- **Avaliações**: depois de concluído, o cliente dá de 1 a 5 estrelas e um comentário. A nota média aparece no catálogo e no perfil.
- **Perfil público do prestador**: serviços ativos, avaliações e número de serviços concluídos.
- **Privacidade**: as rotas públicas não expõem e-mail nem telefone dos usuários.

## Tecnologias

- PHP 8.3+ e Laravel 13
- PostgreSQL 18 (via Docker)
- Filas do Laravel (driver `database`) para enviar os e-mails
- [Mailpit](https://mailpit.axllent.org/) para ver os e-mails em desenvolvimento
- Laravel Sanctum para autenticação
- PHPUnit para testes e Laravel Pint para formatação

## Pré-requisitos

- PHP 8.3 ou superior, com as extensões `pdo_pgsql` e `pgsql`
- [Composer](https://getcomposer.org/)
- [Docker](https://docs.docker.com/get-docker/) com Docker Compose

## Como rodar

```bash
# 1. Instale as dependências
composer install

# 2. Configure o ambiente
cp .env.example .env
php artisan key:generate

# 3. Suba o banco de dados (Postgres na porta 5434) e o Mailpit
docker compose up -d pgsql mailpit

# 4. Crie as tabelas e popule com dados de demonstração
php artisan migrate --seed

# 5. Libere o acesso público às fotos enviadas
php artisan storage:link

# 6. Inicie a API em http://localhost:8000
php artisan serve

# 7. Em outro terminal, inicie a fila (é ela que envia os e-mails)
php artisan queue:work
```

### E-mails em desenvolvimento

Nenhum e-mail sai de verdade: todos vão para o **Mailpit**. Abra **http://localhost:8025** para ver a caixa de entrada.

Os links dos e-mails apontam para o frontend, configurado em `FRONTEND_URL` no `.env` (padrão `http://localhost:5199`).

> O `compose.yaml` também define o container `laravel.test` (Laravel Sail). Para desenvolver, basta subir o serviço `pgsql`, como no passo 3, e rodar a API direto na máquina.

### Dados de demonstração

O comando `php artisan migrate --seed` cria 8 categorias, 5 prestadores, 3 clientes, 14 serviços, pedidos em todos os status e algumas avaliações. **Todos os usuários usam a senha `password`.**

| Perfil | E-mails |
|---|---|
| Prestadores | `ana@example.com`, `bruno@example.com`, `carla@example.com`, `diego@example.com`, `eduardo@example.com` |
| Clientes | `fernanda@example.com`, `gabriel@example.com`, `helena@example.com` |
| Cliente e prestador | `test@example.com` |

O seeder pode ser rodado de novo sem duplicar dados. Para recriar o banco do zero, use `php artisan migrate:fresh --seed`.

### Acessar o banco

```bash
docker compose exec pgsql psql -U sail -d freelance
```

Para usar um cliente gráfico, como DBeaver ou TablePlus, conecte em `127.0.0.1:5434`, banco `freelance`, usuário `sail` e senha `password`.

## Testes

Os testes rodam no banco `testing`, que o container do Postgres cria automaticamente.

```bash
php artisan test
```

Para formatar o código:

```bash
./vendor/bin/pint
```

## Endpoints

Todas as rotas usam o prefixo `/api`. As rotas protegidas exigem o cabeçalho `Authorization: Bearer <token>`, com o token devolvido pelo login.

### Públicas

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/register` | Cadastro (`name`, `email`, `password`, `password_confirmation`) |
| `POST` | `/login` | Login: devolve `user` e `token` |
| `GET` | `/categorias-servico` | Lista de categorias |
| `GET` | `/servicos` | Catálogo de serviços ativos. Filtros: `categoria_servico_id` e `perfil_prestador_id` |
| `GET` | `/servicos/{id}` | Detalhes de um serviço |
| `GET` | `/prestadores/{id}` | Perfil público do prestador, com serviços e avaliações |

### Autenticadas

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/logout` | Encerra a sessão (revoga o token) |
| `GET` / `PUT` | `/profile` | Ver e atualizar o perfil (aceita `avatar` em multipart) |
| `GET` | `/meus-servicos` | Serviços do prestador logado |
| `POST` | `/servicos` | Cria um serviço |
| `PUT` | `/servicos/{id}` | Edita, pausa ou reativa um serviço |
| `DELETE` | `/servicos/{id}` | Exclui um serviço (só se ele não tiver pedidos) |
| `POST` | `/servicos/{id}/fotos` | Envia fotos (`fotos[]`, até 5 por serviço, 4 MB cada) |
| `DELETE` | `/servico-fotos/{id}` | Remove uma foto |
| `POST` | `/pedidos` | Solicita um serviço |
| `GET` | `/meus-pedidos` | Pedidos feitos pelo cliente |
| `GET` | `/pedidos-recebidos` | Pedidos recebidos pelo prestador |
| `PUT` | `/pedidos/{id}/aceitar` | O prestador aceita um pedido pendente |
| `PUT` | `/pedidos/{id}/recusar` | O prestador recusa um pedido pendente |
| `PUT` | `/pedidos/{id}/concluir` | O prestador conclui um pedido aceito |
| `PUT` | `/pedidos/{id}/cancelar` | O cliente cancela um pedido pendente ou aceito |
| `POST` | `/pedidos/{id}/avaliacao` | O cliente avalia um pedido concluído (`nota` de 1 a 5, `comentario`) |
| `GET` | `/pedidos/{id}/mensagens` | Conversa do pedido. Marca como lidas as mensagens da outra parte. Com `?depois_de={id}`, devolve só as novas |
| `POST` | `/pedidos/{id}/mensagens` | Envia uma mensagem (`texto`, até 2000 caracteres). Pedidos recusados ou cancelados ficam só para leitura |

## Estrutura

```
app/
├── Http/Controllers/Api/   # Controllers da API
├── Models/                 # User, PerfilPrestador, PerfilCliente, Servico, ServicoFoto, Pedido, Mensagem, Avaliacao...
├── Notifications/          # E-mails enviados aos usuários
└── Policies/               # Regras de permissão (dono do serviço, participantes do pedido)
database/
├── migrations/
└── seeders/                # Categorias e dados de demonstração
routes/api.php              # Rotas da API
tests/Feature/              # Testes de integração
```
