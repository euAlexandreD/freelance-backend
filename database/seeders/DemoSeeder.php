<?php

namespace Database\Seeders;

use App\Models\CategoriaServico;
use App\Models\Pedido;
use App\Models\PerfilCliente;
use App\Models\PerfilPrestador;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de demonstração para desenvolvimento local.
 * Todos os usuários usam a senha "password".
 */
class DemoSeeder extends Seeder
{
    private const PRESTADORES = [
        [
            'name' => 'Ana Souza', 'email' => 'ana@example.com', 'cidade' => 'São Paulo', 'uf' => 'SP',
            'titulo' => 'Desenvolvedora Full Stack', 'bio' => 'Crio sites e sistemas web com React e Laravel há 6 anos.',
            'servicos' => [
                ['desenvolvimento-web', 'Criação de site institucional responsivo, com painel administrativo.'],
                ['desenvolvimento-web', 'Manutenção e correção de bugs em aplicações Laravel.'],
                ['consultoria', 'Consultoria em arquitetura de software e boas práticas.'],
            ],
        ],
        [
            'name' => 'Bruno Lima', 'email' => 'bruno@example.com', 'cidade' => 'Porto Alegre', 'uf' => 'RS',
            'titulo' => 'Designer Gráfico', 'bio' => 'Identidade visual, logotipos e materiais para redes sociais.',
            'servicos' => [
                ['design-grafico', 'Criação de logotipo e manual de identidade visual.'],
                ['design-grafico', 'Pacote de 10 artes para Instagram.'],
                ['marketing-digital', 'Planejamento de conteúdo mensal para redes sociais.'],
            ],
        ],
        [
            'name' => 'Carla Mendes', 'email' => 'carla@example.com', 'cidade' => 'Curitiba', 'uf' => 'PR',
            'titulo' => 'Tradutora e Revisora', 'bio' => 'Tradução inglês/espanhol ↔ português e revisão de textos acadêmicos.',
            'servicos' => [
                ['redacao-e-traducao', 'Tradução de documentos inglês ↔ português (por lauda).'],
                ['redacao-e-traducao', 'Revisão ortográfica e ABNT de TCCs e artigos.'],
                ['aulas-particulares', 'Aulas de inglês online para iniciantes.'],
            ],
        ],
        [
            'name' => 'Diego Rocha', 'email' => 'diego@example.com', 'cidade' => 'Belo Horizonte', 'uf' => 'MG',
            'titulo' => 'Fotógrafo', 'bio' => 'Fotografia de eventos, produtos e ensaios.',
            'servicos' => [
                ['fotografia-e-video', 'Cobertura fotográfica de eventos (até 4 horas).'],
                ['fotografia-e-video', 'Fotos de produtos para e-commerce com fundo branco.'],
            ],
        ],
        [
            'name' => 'Eduardo Alves', 'email' => 'eduardo@example.com', 'cidade' => 'Florianópolis', 'uf' => 'SC',
            'titulo' => 'Eletricista e Reparos', 'bio' => 'Instalações elétricas, pequenos reparos e montagem de móveis.',
            'servicos' => [
                ['reformas-e-manutencao', 'Instalação de tomadas, chuveiros e luminárias.'],
                ['reformas-e-manutencao', 'Montagem de móveis planejados e de loja.'],
            ],
        ],
    ];

    private const CLIENTES = [
        ['name' => 'Fernanda Costa', 'email' => 'fernanda@example.com', 'cidade' => 'São Paulo', 'uf' => 'SP'],
        ['name' => 'Gabriel Martins', 'email' => 'gabriel@example.com', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ'],
        ['name' => 'Helena Ribeiro', 'email' => 'helena@example.com', 'cidade' => 'Recife', 'uf' => 'PE'],
    ];

    private const PEDIDOS = [
        'Preciso para o próximo mês, podemos conversar sobre o prazo?',
        'Gostaria de um orçamento detalhado.',
        'Já tenho algumas referências, posso enviar?',
        'É urgente, consegue começar esta semana?',
        null,
    ];

    public function run(): void
    {
        Model::unguarded(function () {
            $categorias = CategoriaServico::query()->pluck('id', 'slug');

            foreach (self::PRESTADORES as $dados) {
                $user = $this->usuario($dados);
                $perfil = PerfilPrestador::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['titulo' => $dados['titulo'], 'bio' => $dados['bio']],
                );

                foreach ($dados['servicos'] as [$slug, $descricao]) {
                    Servico::query()->updateOrCreate(
                        ['perfil_prestador_id' => $perfil->id, 'descricao' => $descricao],
                        ['categoria_servico_id' => $categorias[$slug], 'ativo' => true],
                    );
                }
            }

            $clientes = collect(self::CLIENTES)->map(function (array $dados) {
                $user = $this->usuario($dados);

                return PerfilCliente::query()->firstOrCreate(['user_id' => $user->id]);
            });

            // O usuário de teste vira cliente e prestador, para testar os dois lados.
            $teste = User::query()->where('email', 'test@example.com')->first();
            if ($teste) {
                $clientes->push(PerfilCliente::query()->firstOrCreate(['user_id' => $teste->id]));
                $perfilTeste = PerfilPrestador::query()->firstOrCreate(
                    ['user_id' => $teste->id],
                    ['titulo' => 'Desenvolvedor', 'bio' => 'Usuário de testes.'],
                );
                Servico::query()->firstOrCreate(
                    ['perfil_prestador_id' => $perfilTeste->id, 'descricao' => 'Desenvolvimento de landing pages.'],
                    ['categoria_servico_id' => $categorias['desenvolvimento-web'], 'ativo' => true],
                );
            }

            if (Pedido::query()->exists()) {
                return;
            }

            $status = ['pendente', 'pendente', 'aceito', 'recusado', 'concluido', 'cancelado'];
            $servicos = Servico::query()->get();

            foreach ($clientes as $i => $cliente) {
                $donoDoPerfil = $cliente->user_id;
                $disponiveis = $servicos->reject(
                    fn (Servico $s) => $s->perfilPrestador->user_id === $donoDoPerfil,
                )->shuffle()->take(4);

                foreach ($disponiveis->values() as $j => $servico) {
                    Pedido::query()->create([
                        'perfil_cliente_id' => $cliente->id,
                        'servico_id' => $servico->id,
                        'status' => $status[($i + $j) % count($status)],
                        'descricao_pedido' => self::PEDIDOS[($i + $j) % count(self::PEDIDOS)],
                    ]);
                }
            }
        });
    }

    private function usuario(array $dados): User
    {
        return User::query()->updateOrCreate(
            ['email' => $dados['email']],
            [
                'name' => $dados['name'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'cidade' => $dados['cidade'],
                'uf' => $dados['uf'],
            ],
        );
    }
}
