---
name: tdd-phpunit-laravel
description: >
  Orienta agentes de código ao escrever testes seguindo TDD com PHPUnit em
  projetos Laravel. Use este skill sempre que o desenvolvedor pedir para
  escrever testes, criar código de produção (o skill exige teste primeiro),
  corrigir bugs, refatorar, ou quando qualquer tarefa envolver PHPUnit,
  testes unitários, testes de feature, factories, mocks, ou a suíte de
  testes do projeto. Também disparar quando o desenvolvedor mencionar
  "cobertura", "test", "TDD", "RED GREEN", "PHPStan" no contexto de
  verificação, ou pedir para modificar código existente sem mencionar
  testes. Se o desenvolvedor pedir para implementar qualquer funcionalidade
  nova (endpoint, job, service, command, listener), este skill se aplica —
  o código de produção não pode existir sem teste. Não esperar o
  desenvolvedor pedir testes explicitamente; se a tarefa envolve código
  PHP no projeto Laravel, consulte este skill.
---

# TDD com PHPUnit no Laravel

> Skill de orientação para agentes de código (Claude Code, Codex, Cursor)
> ao escrever testes seguindo Test-Driven Development em projetos Laravel
> desta empresa.
>
> **Esta skill não ensina o que é TDD.** Ela codifica as decisões
> específicas do nosso contexto que um agente erraria sozinho. Quando uma
> regra abaixo disser "PARE", o agente deve interromper a geração de
> código e avisar o desenvolvedor antes de prosseguir.

---

## 0. Stack assumida

- PHP 8.4
- Laravel (versão corrente do projeto)
- PHPUnit como test runner (NÃO Pest)
- Orchestra Testbench, quando o alvo é um package (incluindo o próprio
  `laravel-standards`)
- Banco de testes: SQLite em memória por padrão; MySQL real apenas quando
  o teste exige comportamento específico do MySQL (ver seção 4)

Os testes são declarados com **prefixo `test_` no nome do método**, não
com atributos `#[Test]` nem anotações `@test`. Esta é uma convenção fixa
do time: preferimos a forma explícita, sem mágica, onde o runner encontra
o teste pelo nome.

```php
public function test_it_creates_a_user_with_valid_data(): void
{
    // ...
}
```

Os scripts de verificação (`composer test`, `composer analyse`,
`composer lint`) são providos pelo pacote `laravel-standards` via
composer scripts compartilhados. Consulte o `composer.json` do projeto
para confirmar os nomes exatos antes de rodar.

---

## 1. O loop inegociável

O ciclo é **RED → GREEN → REFACTOR**, nesta ordem, sempre:

1. **RED** — escreva um teste que falha porque o comportamento ainda não
   existe. Rode-o e confirme que ele falha *pela razão certa* (não por
   erro de sintaxe ou classe inexistente — a menos que a classe
   inexistente seja exatamente o próximo passo).
2. **GREEN** — escreva o **mínimo** de código de produção para o teste
   passar. Nada além disso.
3. **REFACTOR** — melhore o código mantendo os testes verdes. Rode a
   suíte a cada alteração.

### Regra de parada (PARE)

Se o desenvolvedor pedir para **escrever ou alterar código de produção
sem um teste que o cubra**, PARE. Não gere o código silenciosamente.
Responda algo como:

> "Esse comportamento ainda não tem teste cobrindo. Quer que eu escreva
> o teste primeiro (RED) e só depois implemente? Se for uma alteração em
> código existente sem cobertura, recomendo escrever um teste de
> caracterização antes de tocar nele."

A única exceção é código sem lógica (DTOs triviais, bindings de
container, configuração). Na dúvida, trate como se precisasse de teste.

**Em contexto não-interativo** (CI, batch, pipeline onde não é possível
perguntar): recuse a tarefa, registre o motivo no output, e não gere
código de produção sem teste. Nunca assuma silenciosamente que "está
tudo bem".

### Regra de cobertura de regressão (PARE)

Antes de **modificar** código existente, verifique se há teste cobrindo
o comportamento atual. Se não houver, PARE e proponha escrever um
**teste de caracterização** (um teste que captura o comportamento atual,
mesmo que "errado") antes de mexer. Mudar código sem rede de segurança
é proibido.

---

## 2. Roteamento: Feature vs Unit

A confusão mais comum no Laravel. A regra é sobre **dependências**, não
sobre "tamanho" ou "importância" do que está sendo testado.

### Regra dura

- **Unit** — o código sob teste é lógica pura, isolável, que NÃO toca o
  framework: sem banco, sem container, sem eventos, sem HTTP, sem
  filesystem, sem facades. Exemplos: um Value Object, um cálculo, um
  formatador, um método estático puro. Estende
  `PHPUnit\Framework\TestCase` diretamente (ou o TestCase unit do projeto
  que NÃO inicializa a aplicação).

- **Feature** — o código toca qualquer parte do framework. Estende o
  `Tests\TestCase` que inicializa a aplicação Laravel
  (`CreatesApplication`).

### O que engana

Estes "parecem unit" mas são **Feature**, porque tocam o framework:

- Um **Job** que persiste no banco ou despacha eventos → Feature.
- Um **Service** que injeta um repositório Eloquent → Feature (a menos
  que você injete um duplo de teste do repositório; aí pode ser unit).
- Qualquer teste que use `Model::factory()`, `config()`, `event()`,
  `Http::`, `Storage::`, `Queue::` → Feature.
- Qualquer teste que chame um endpoint (`$this->getJson(...)`) → Feature.

### Heurística de uma linha

> Se o teste precisa que o Laravel esteja "ligado" para funcionar, é
> Feature. Se roda com o framework completamente desligado, é Unit.

Na prática, a maioria dos testes úteis em uma aplicação Laravel são
Feature. Não force algo a ser Unit só para parecer "mais TDD". Um teste
Unit que precisa de cinco mocks para isolar o framework geralmente
deveria ser um teste Feature com banco de verdade.

---

## 3. Estrutura do teste: Arrange-Act-Assert

Todo teste segue **AAA**, com as três seções visualmente separadas por
linha em branco. Sem comentários `// Arrange` — a separação por espaço
é suficiente.

```php
public function test_it_marks_an_order_as_paid(): void
{
    $order = Order::factory()->pending()->create();

    $order->markAsPaid();

    $this->assertTrue($order->fresh()->isPaid());
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => OrderStatus::Paid->value,
    ]);
}
```

### Use asserts específicos do Laravel

Prefira os asserts do framework aos genéricos do PHPUnit — eles dão
mensagens de erro melhores e expressam intenção:

| Em vez de | Use |
|-----------|-----|
| `assertTrue(User::where(...)->exists())` | `assertDatabaseHas('users', [...])` |
| `assertEquals(0, User::count())` | `assertDatabaseEmpty('users')` ou `assertDatabaseCount` |
| checar atributos manualmente | `assertModelExists($model)` / `assertModelMissing($model)` |
| `assertEquals(200, $response->status())` | `$response->assertOk()` / `assertStatus(...)` |
| parsear JSON na mão | `$response->assertJson([...])` / `assertJsonPath(...)` |
| verificar soft delete manualmente | `assertSoftDeleted($model)` / `assertNotSoftDeleted($model)` |

---

## 4. Estratégia de banco de dados

### Árvore de decisão

1. **O teste depende de comportamento específico do MySQL?** (full-text
   search, JSON paths nativos, tipos espaciais, collations, locking)
   → Use **MySQL real** com a trait `DatabaseTransactions` (faz rollback
   ao fim de cada teste, sem recriar o schema). Marque a classe com
   `#[Group('mysql')]` para segmentar na CI.

2. **O teste é de feature comum** (CRUD, validação, regras de negócio)?
   → Use **SQLite em memória** com `RefreshDatabase`. Esta é a opção
   padrão para a maioria dos testes.

3. **O teste é unit puro** (não toca banco)?
   → Não use trait de banco nenhuma. Se sentiu vontade de usar, o teste
   provavelmente é Feature (volte à seção 2).

### Regras

- **Nunca** use `RefreshDatabase` contra o banco de desenvolvimento. O
  ambiente de teste (`phpunit.xml`) deve apontar para banco/conexão
  dedicado.
- `RefreshDatabase` roda as migrations uma vez e envolve cada teste em
  transação. `DatabaseMigrations` recria tudo a cada teste — evite salvo
  necessidade real.
- Em SQLite in-memory, cada conexão tem seu próprio banco. Configure
  `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:` no ambiente de teste.
- Atenção a diferenças SQLite × MySQL que causam falso-verde: SQLite é
  leniente com tipos, foreign keys (precisam ser habilitadas), e algumas
  funções SQL não existem. Se um teste depende dessas diferenças, ele
  pertence ao grupo `mysql`.

### Paralelismo

`php artisan test --parallel` cria um banco por processo (sufixado com
o número do worker, ex.: `meu_banco_test_2`) e roda as migrations em
cada um. Isso funciona com MySQL e com SQLite em arquivo, mas **não**
com SQLite in-memory: `:memory:` é privado por conexão, então cada
worker fica com um schema vazio que não persiste entre testes do mesmo
processo da forma esperada — resultado típico é falsos negativos
intermitentes.

Se a suíte crescer e o time quiser adotar paralelismo:

- Troque para SQLite em arquivo (`database/testing.sqlite`) ou MySQL no
  ambiente de teste.
- Garanta que o usuário do banco tem permissão para criar os bancos
  sufixados (MySQL).
- Rode com `--recreate-databases` na primeira execução após mudanças de
  migration, para forçar o rebuild.

Não ative `--parallel` sem alinhamento prévio com o time — a mudança
mexe na configuração de banco do ambiente de teste e pode mascarar
problemas de isolamento entre testes.

---

## 5. O que mockar e o que NÃO mockar

### NUNCA mocke o Eloquent

Não crie mocks de Models nem do query builder. Use **factories** e banco
de teste real. Mockar Eloquent produz testes frágeis que testam a sua
expectativa do ORM, não o comportamento real.

```php
// ERRADO
$user = Mockery::mock(User::class);
$user->shouldReceive('getAttribute')->with('name')->andReturn('João');

// CERTO
$user = User::factory()->create(['name' => 'João']);
```

### SEMPRE use os fakes nativos para serviços externos

| Dependência externa | Fake |
|---------------------|------|
| Chamadas HTTP de saída | `Http::fake([...])` + `Http::assertSent(...)` |
| Filas / Jobs | `Queue::fake()` + `Queue::assertPushed(...)` (ou `Bus::fake()`) |
| E-mails | `Mail::fake()` + `Mail::assertSent(...)` |
| Notificações | `Notification::fake()` + `assertSentTo(...)` |
| Eventos | `Event::fake()` + `Event::assertDispatched(...)` |
| Filesystem | `Storage::fake('disk')` + `Storage::assertExists(...)` |
| Tempo | `$this->travelTo(...)` / `Carbon::setTestNow(...)` |

### Quando mockar de verdade (Mockery)

Reserve mocks manuais para **interfaces de serviços do próprio domínio**
— gateways de pagamento, clientes de APIs de terceiros encapsulados
atrás de uma interface sua. Mocke a *abstração*, nunca a implementação
concreta nem o cliente HTTP cru (para HTTP cru, use `Http::fake()`).

### Regra de ouro

> Se você precisa de mais de 2-3 mocks para um teste, ou o código está
> mal desenhado (acoplamento alto), ou o teste deveria ser Feature com
> dependências reais. PARE e sinalize isso ao desenvolvedor em vez de
> empilhar mocks.

---

## 6. Autenticação e autorização em testes

### Autenticação

Use **`actingAs()`** para simular um usuário autenticado. Nunca monte
headers de autenticação manualmente nem crie tokens reais dentro do
teste (exceto se o teste é justamente sobre o fluxo de login/token).

```php
public function test_authenticated_user_can_list_orders(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/orders');

    $response->assertOk();
}
```

Para APIs com **Sanctum**, use `Sanctum::actingAs()` quando precisar de
abilities/scopes no token:

```php
use Laravel\Sanctum\Sanctum;

public function test_user_with_read_scope_can_list_but_not_create(): void
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['orders:read']);

    $this->getJson('/api/orders')->assertOk();
    $this->postJson('/api/orders', [...])->assertForbidden();
}
```

Para testar que **guests** são bloqueados, não passe `actingAs`:

```php
public function test_guests_cannot_access_orders(): void
{
    $this->getJson('/api/orders')->assertUnauthorized();
}
```

### Autorização (Policies e Gates)

Teste policies **via endpoint**, não isoladamente. O que importa é o
comportamento observável — o usuário recebe 403 ou não:

```php
public function test_non_owner_cannot_update_order(): void
{
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->for($owner)->create();

    $response = $this->actingAs($other)
        ->putJson("/api/orders/{$order->id}", ['status' => 'paid']);

    $response->assertForbidden();
}
```

---

## 7. Validação e Form Requests

Teste validação **pelo endpoint**, verificando que dados inválidos
retornam 422 e que a estrutura de erros é a esperada. Não teste Form
Requests isoladamente (é acoplamento à implementação).

```php
public function test_it_returns_422_when_email_is_missing(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/contacts', [
            'name' => 'João',
            // email ausente de propósito
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrorFor('email');
}
```

Use **data providers** para variações de validação (ver seção 9 sobre
data providers).

---

## 8. Exception handling em testes

### Quando usar `withoutExceptionHandling()`

Use **apenas** quando quiser que a exceção real estoure no teste para
inspecioná-la (ex.: `$this->expectException(...)`). Sem isso, o Laravel
captura a exceção e retorna uma HTTP response — que geralmente é o que
você quer testar.

```php
// CORRETO — testa o comportamento HTTP visível
public function test_it_returns_404_for_nonexistent_order(): void
{
    $this->actingAs(User::factory()->create())
        ->getJson('/api/orders/99999')
        ->assertNotFound();
}

// CORRETO — testa a exceção de domínio diretamente
public function test_it_throws_when_paying_cancelled_order(): void
{
    $this->withoutExceptionHandling();

    $order = Order::factory()->cancelled()->create();

    $this->expectException(OrderNotPayableException::class);

    $order->markAsPaid();
}
```

**Não** coloque `withoutExceptionHandling()` como padrão no `setUp()`.
Cada teste decide se precisa — a maioria dos testes de endpoint não
precisa, porque o comportamento observável é o status HTTP.

---

## 9. Factories, dados e convenções

### Factories e dados de teste

- Use **factories** para criar models, sempre. Nunca insira via
  `DB::table()` em testes salvo para setup sem model.
- Use **states** nomeados para variações relevantes (`->pending()`,
  `->admin()`, `->cancelled()`), em vez de passar arrays repetidos.
- Crie **apenas os dados que o teste precisa**. Não popule o banco com
  50 registros "por garantia" — isso esconde dependências e deixa lento.
- Não dependa de **seeders** da aplicação dentro de testes. Seeders são
  para o ambiente, não para o arrange do teste.

### Nome do método

`test_` + descrição do comportamento em snake_case, lendo como frase:

```php
public function test_it_rejects_an_order_below_the_minimum_amount(): void
public function test_guests_cannot_access_the_dashboard(): void
public function test_it_returns_422_when_the_email_is_invalid(): void
```

Descreva **comportamento e resultado**, não implementação.
`test_calls_save_method` é ruim; `test_it_persists_the_user` é bom.

### Data providers

Quando o mesmo comportamento é verificado com várias entradas, use
data provider **nomeado** (chaves string, não índices numéricos):

```php
public static function invalidEmailProvider(): array
{
    return [
        'sem arroba'      => ['plainaddress'],
        'sem domínio'     => ['user@'],
        'com espaço'      => ['user @example.com'],
        'string vazia'    => [''],
    ];
}

#[DataProvider('invalidEmailProvider')]
public function test_it_rejects_invalid_emails(string $email): void
{
    // ...
}
```

> Nota: usamos `#[DataProvider]` (atributo) por ser obrigatório no
> PHPUnit moderno — isto é independente da convenção de nomear o *teste*
> com `test_`.

### setUp e agrupamento

Use `setUp()` apenas para o que é comum a **todos** os testes da classe.
Sempre chame `parent::setUp()` na primeira linha. Não esconda o arrange
específico de um teste dentro do `setUp`.

Uma classe de teste por classe de produção como regra geral. Use
`#[Group(...)]` para marcar testes lentos, dependentes de MySQL, ou de
integração externa.

---

## 10. Anti-padrões — recuse ou sinalize

Quando detectar qualquer um destes, PARE e avise o desenvolvedor:

- **Testar implementação em vez de comportamento** — asserts sobre
  métodos internos chamados, em vez do resultado observável.
- **Teste sem assert** — um teste que só executa código e nunca verifica
  nada. Recuse.
- **Asserts triviais** — `assertTrue(true)` ou afins. Sinalize.
- **Testes dependentes de ordem** — um teste que só passa se outro rodou
  antes. Cada teste deve ser independente e idempotente.
- **Mock do que está sob teste** — mockar a própria classe que o teste
  deveria exercer. Recuse.
- **Excesso de mocks** (ver seção 5) — sinal de design ruim ou de teste
  no nível errado.
- **Múltiplos comportamentos num teste só** — se o nome precisa de
  "and"/"e", provavelmente são dois testes.
- **Lógica condicional no teste** (`if`/`foreach` decidindo o que
  assertar) — geralmente indica que deveriam ser casos separados num
  data provider.
- **`withoutExceptionHandling()` no setUp** — mascara o comportamento
  HTTP real. Cada teste decide se precisa.
- **Montar headers de auth manualmente** em vez de usar `actingAs()`.

---

## 11. Checklist de verificação

Após implementar, antes de considerar a tarefa concluída:

1. A suíte inteira passa? (`composer test` / `php artisan test`) — não
   apenas o teste novo.
2. O teste novo **falha** se você reverter o código de produção?
   (confirma que o teste realmente testa algo)
3. PHPStan passa no nível configurado do projeto? (`composer analyse`)
4. Pint está satisfeito? (`composer lint` / `vendor/bin/pint --test`)
5. Nenhum teste foi marcado como `skipped`/`incomplete` sem
   justificativa explícita.
6. Não há `dd()`, `dump()`, `ray()` ou `sleep()` esquecidos.

Se qualquer item falhar, a tarefa **não** está concluída. Reporte o
estado real ao desenvolvedor; não declare sucesso prematuro.

> Os scripts `composer test`, `composer analyse` e `composer lint` são
> providos pelo pacote `laravel-standards`. Se não estiverem disponíveis,
> confira se o pacote está instalado e o `composer.json` do projeto
> inclui os scripts.

---

## 12. Exemplos completos de fluxo (referência)

### Exemplo 1 — Feature test (regra de negócio com banco)

Tarefa: "um pedido só pode ser pago se estiver pendente".

**RED** — escreve o teste primeiro:

```php
public function test_it_throws_when_paying_a_non_pending_order(): void
{
    $this->withoutExceptionHandling();

    $order = Order::factory()->cancelled()->create();

    $this->expectException(OrderNotPayableException::class);

    $order->markAsPaid();
}
```

Roda → falha (método/exceção não existem). RED confirmado.

**GREEN** — mínimo para passar:

```php
public function markAsPaid(): void
{
    if ($this->status !== OrderStatus::Pending) {
        throw new OrderNotPayableException($this);
    }

    $this->update(['status' => OrderStatus::Paid]);
}
```

Roda → passa. Roda a suíte inteira → verde.

**REFACTOR** — extrai a guarda para um método `isPayable()` se houver
duplicação, rodando a suíte a cada passo. Verde mantido. Concluído após
o checklist da seção 11.

### Exemplo 2 — Unit test (lógica pura sem framework)

Tarefa: "formatar CPF com pontos e traço".

**RED**:

```php
// tests/Unit/CpfFormatterTest.php
use PHPUnit\Framework\TestCase;

class CpfFormatterTest extends TestCase
{
    public function test_it_formats_a_raw_cpf_string(): void
    {
        $formatted = CpfFormatter::format('12345678901');

        $this->assertSame('123.456.789-01', $formatted);
    }

    public function test_it_throws_for_invalid_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CpfFormatter::format('123');
    }
}
```

Roda → falha (classe não existe). RED confirmado.

**GREEN**:

```php
// app/ValueObjects/CpfFormatter.php
class CpfFormatter
{
    public static function format(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);

        if (strlen($digits) !== 11) {
            throw new \InvalidArgumentException(
                "CPF deve ter 11 dígitos, recebeu " . strlen($digits)
            );
        }

        return substr($digits, 0, 3) . '.' .
               substr($digits, 3, 3) . '.' .
               substr($digits, 6, 3) . '-' .
               substr($digits, 9, 2);
    }
}
```

Roda → passa. Note: este teste estende `PHPUnit\Framework\TestCase`
diretamente — não precisa do Laravel "ligado". Não usa trait de banco.
É unit puro.