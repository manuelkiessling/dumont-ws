# Architecture Book

How the application is built: its verticals and their layers, how verticals work together, and the conventions of its
code. Most rules here are enforced by `composer quality` and `composer test`; the [devbook](devbook.md) says how to run
them.


## Verticals and layers

The code is organized by verticals (feature modules). Each folder under `src/` is a vertical with a bounded context of
its own, and each vertical has the layers it needs:

```
src/
└── FeatureName/
    ├── Domain/           # The business: its models, rules and use cases
    ├── Facade/           # What other verticals may use (the port between verticals)
    ├── Infrastructure/   # Adapters to systems outside the vertical, such as files
    └── Presentation/     # What a person sees and does: terminal screens and their commands
```

- **A layer is a concern, not a kind of class.** A console command stands in the layer whose work it starts: the
  command that opens a screen is the Presentation's. Ask which concern a class serves, not where others of its kind
  are.
- A vertical omits the layers it does not need. Only add a `Facade/` when another vertical actually needs one.
- `Common/` is shared by every vertical, for what no vertical owns, such as the current time
  (`Common\Domain\Service\DateAndTimeService`). Every vertical may use `Common`; `Common` uses no vertical, not even
  a facade (`FeatureBoundariesArchTest`).

### Domain

- **Models** (`Domain/Model`) hold business state and decide only from what they are given. A model never reads the
  clock, draws randomness, or calls a service; whoever calls it passes the time and new IDs in, as
  `TodoItem::markedDoneAt($doneAt)` takes its time. `PureDomainCodeRule` holds this.
- **The domain service** (`Domain/Service/<Name>DomainService`) has one public method per use case. It validates,
  takes the current time once per command, makes new IDs, and asks its ports. Every rule of the vertical is decided
  here or in its models, never in a screen or a store.
- **Ports** are interfaces under `Domain`, such as `TodoItemStoreInterface`, implemented by an adapter under
  `Infrastructure`. What a domain class takes in its constructor from outside `Domain` is an interface
  (`DomainReachesOutThroughInterfacesRule`), and the domain names no class under any `Infrastructure`
  (`DomainNamesNoInfrastructureRule`).
- **Refusals** are exceptions under `Domain/Exception` that extend the vertical's abstract `<Vertical>RefusedException`
  (as `TodoRefusedException`), made by named factories (`InvalidItemTextException::tooLong(200)`). The message is
  written for the person in front of the screen, who sees it as it is. `ConventionsTest` holds that every class under
  `Domain/Exception` extends its vertical's abstract refusal.

### Infrastructure

- An adapter implements a port of its vertical's domain. A store keeps the vertical's data in one JSON file of its
  own, under `var/data/` (and `var/test/` in the test environment); the file's path is a parameter in
  `config/services.yaml` (`app.todo_items_file`), bound by name to the store's constructor argument.
  `tests/Integration/Common/ConfigurationTest.php` holds that every `app.*_file` lies under `var/data/`, and under
  `var/test/` in the tests, so that a test never touches the application's data.
- A store checks what it decodes: a file can hold anything, so it throws instead of passing on what is not what it
  expects. It writes the whole file at once.


## Verticals working together

Verticals are isolated from each other's internals. A vertical uses another one only through that vertical's
`Facade`:

- **Facade interfaces** (`Facade/<Name>Interface`) with their **DTOs** (`Facade/Dto/<Name>Dto`), `final readonly`
  classes of simple fields (`ConventionsTest`). A facade passes and returns DTOs, value objects, enums and primitives, never models or any
  other class of an internal layer (`FacadesExposeNoInternalsRule`). A facade class stands behind the interface where it
  has work of its own, such as turning models into DTOs; a class that would only forward every call implements the
  interface itself (`FacadeClassesHaveWorkOfTheirOwnRule`).
- **Facade events** (`Facade/SymfonyEvent/<Name>SymfonyEvent`), announced by the vertical's domain service through
  PSR-14's `EventDispatcherInterface`, and heard by a listener (`#[AsEventListener]`) in another vertical's
  `Infrastructure`. A vertical that must react to what happens in another one listens; the vertical where it happens
  does not know who listens. Event classes end in `SymfonyEvent` (`SymfonyEventClassNameRule`,
  `DispatchedSymfonyEventNameRule`).
- `config/services.yaml` excludes the verticals' models and exceptions from the services, as they are data, not
  services. When a vertical gets a `Facade/Dto/` or a `Facade/SymfonyEvent/`, add them to the excludes the same way
  (`'../src/*/Facade/Dto/'`, `'../src/*/Facade/SymfonyEvent/'`). `ConfigurationTest` holds that no model, refusal,
  DTO or event is a service.

**Dependencies point one way.** No cycle between verticals: if A uses B's facade, B neither uses A's facade nor names
any of A's classes; B announces an event that A hears instead. The direction is declared for
`VerticalDependencyDirectionRule` in `phpstan.dist.neon` (`'TimeTracking -> Todo'` would say that `TimeTracking` may
use `Todo`); every vertical is listed there, also one that depends on nothing (`'Todo ->'`). `ConventionsTest` holds
that every vertical is listed and that the declared dependencies form no cycle.

The architecture test `tests/Architecture/FeatureBoundariesArchTest.php` fails for any class of a vertical that names
another vertical's `Domain`, `Infrastructure` or `Presentation`.


## Data transfer

- DTOs are `final readonly` classes with promoted constructor properties, flat rather than nested.
- What crosses a class boundary is never an associative array. An array in a public or protected signature is a
  `list<T>`, annotated with PHPDoc (`@param list<TodoItem> $items`, `@return list<TodoItem>`)
  (`NoAssociativeArraysAcrossBoundariesRule`). Anything associative becomes a DTO.
- Every PHPDoc tag starts a line of its own: after text, or after another tag on its line, PHPDoc reads it as text,
  and the type it gives is lost (`PhpDocTagsStartALineRule`).


## Time and IDs

- The current time comes from `DateAndTimeService::getDateTimeImmutable()`, never from `new DateTimeImmutable()`,
  `time()` or `date()` (`NoDirectDateTimeUsageRule`). All times are UTC. Reading a stored time back
  (`DateTimeImmutable::createFromFormat()` in a store) is fine.
- IDs are UUIDs made with `Uuid::v7()` (`symfony/uid`) by a domain service and passed into the model. No other
  layer makes IDs or draws randomness (`RandomnessOnlyInDomainServicesRule`).


## The Todo vertical

The worked example to copy:

- **Model** `TodoItem`: an ID, one line of text, when it was added, and when it was done, if it was.
- **Domain service** `TodoListDomainService`: `items()`, `addItem()`, `markItemDone()`, `deleteItem()`. An item is one
  line of text of at most 200 characters, trimmed. Marking a done item done again changes nothing and succeeds. An item
  that is not on the list can be neither marked done nor deleted.
- **Port** `TodoItemStoreInterface`, implemented by `JsonFileTodoItemStore` (`var/data/todo_items.json`).
- **Refusals** `InvalidItemTextException` and `ItemNotOnTheListException`, under `TodoRefusedException`.
- **Screen** `TodoListScreen` and its command `TodoCommand` (`app:todo`), see the [TUI book](tuibook.md).
- **Facade** `TodoListFacadeInterface` (`items()` as `TodoItemDto`s: ID, text, done), implemented by `TodoListFacade`.
- **Facade events** `ItemMarkedDoneSymfonyEvent` and `ItemDeletedSymfonyEvent`, dispatched by `TodoListDomainService`
  (the first only when the item was open) with the item's ID.
- **Tests** on every level: `tests/Unit/Todo` (the domain service with an in-memory store, the screen on a virtual
  terminal) and `tests/Integration/Todo` (the JSON store as the container wires it).


## The TimeTracking vertical

Time spent on the items of the todo list. It depends on `Todo` (declared in `phpstan.dist.neon`) and uses only its
facade and events.

- **Models** `TimePeriod` (an item's ID, when its timer was started and, once it was stopped, when; a period without a
  stop is the running timer) and `TrackedItem` (an item with its text, whether it is done, its seconds and since when
  its timer runs).
- **Domain service** `TimeTrackingDomainService`: `trackedItems()` (the items of the todo list, via the facade, with the
  sum of all their periods), `startTimer()`, `stopRunningTimer()`, `stopTimerOf()`. Only one timer runs at a time:
  starting one stops the other. Starting the timer that runs changes nothing. A timer cannot be started on a done item
  (`ItemIsDoneException`) or one that is not on the list (`ItemNotOnTheListException`). Times are cut to the whole
  second when taken. An item's time is the sum of its periods, a running period counted up to now.
- **Port** `TimePeriodStoreInterface`, implemented by `JsonFileTimePeriodStore` (`var/data/time_periods.json`). It
  keeps the periods of all items, so that the time of a deleted item is kept (it is not shown, as the screen lists the
  todo list's items).
- **Listener** `StopTimerOfItemListener` (Infrastructure) hears the Todo events and stops the item's timer when it is
  marked done or deleted. It runs in the process of the todo list screen, as the files are all the screens share.
- **Screen** `TimeTrackingScreen` and its command `TimeTrackingCommand` (`app:time`): Enter starts the selected item's
  timer, `s` stops the running one. The command refreshes the screen every second (see the [TUI book](tuibook.md#refreshing)),
  which counts a running time up and shows changes made on the todo list in another terminal. Quitting does not stop a
  timer: it is a period in the file.
- **Tests**: `tests/Unit/TimeTracking` (domain service with an in-memory store and a stand-in for the todo list facade,
  the screen on a virtual terminal), `tests/Integration/TimeTracking` (the JSON store, and the listener with the real
  services of both verticals).
- Two processes may write `var/data/time_periods.json` at once; each write is the whole file under a lock, but a
  read-modify-write is not atomic, so the last of two writes within the same instant wins.


## Adding a new vertical

1. Name its use cases: what it does, and what it refuses.
2. Create `src/<Vertical>/` with the layers it needs: models, a domain service, ports and refusals under `Domain`;
   adapters under `Infrastructure`; a screen and its command under `Presentation`.
3. If it keeps data: a store port, a JSON store, and the file's parameter in `config/services.yaml`, with its
   `when@test` path under `var/test/`.
4. If it needs another vertical: use that vertical's facade, and add the facade first where there is none. If another
   vertical must learn of what happens in it, or it of what happens in another: a facade event and a listener.
5. Declare its dependencies in `phpstan.dist.neon`.
6. Tests on every level, as for the Todo vertical: `tests/Unit/<Vertical>` with a test of every domain service and
   every screen, and `tests/Integration/<Vertical>`.
7. A section `## The <Vertical> vertical` in this book, as for the Todo vertical, and in the README how a person uses
   it, naming its commands.

`ConventionsTest` holds steps 5 to 7.


## Coding conventions

Enforced by PHPStan (level 10, 100 % type coverage, the ergebnis rules and the project's rules in
`tools/PhpStan/Rules`) and PHP CS Fixer:

- Every file starts with `declare(strict_types=1);`. Target PHP 8.5 and use its features.
- No named arguments in calls (in attributes they are fine). Where a library's constructor has many optional
  parameters, use its fluent methods instead, as `new Style()->withBold()->withColor('cyan')`.
- No `isset()`, `switch` (use `match`), `compact()`, `eval`, `@` or references (`&`).
- Native types everywhere; narrow `mixed` from outside with `is_string()` and the like.
- A kind, a type, a status or a state is an enum case, never a string (`ClosedSetsAreEnumsRule`).
- Classes are `final` (and `readonly` where they can be); dependencies come in through the constructor and are
  autowired.
- Use `mb_*` functions for strings.
- No code ends the process (`exit`, `die`); the architecture tests hold it.
- Use only a library's public API: nothing that it marks `@internal` (`NoInternalApiOfLibrariesRule`).
- Every package the code uses is declared in `composer.json`, also one that another package brings along anyway
  (`composer-dependency-analyser`, run by `composer quality`).
