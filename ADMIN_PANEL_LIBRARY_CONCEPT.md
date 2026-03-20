# Admin Panel Library Concept

## Idea

Это концепт Laravel-библиотеки для типовых внутренних админок:

- установил пакет;
- получил готовую `/admin`-панель;
- настроил ресурсы декларативно;
- при необходимости вышел в кастомные страницы и сложные workflow.

Цель не в том, чтобы копировать Filament или Nova по внутренней реализации, а в том, чтобы дать такой же уровень DX:

- быстрое создание CRUD-ресурсов;
- единый layout;
- единый UX таблиц, форм и действий;
- predictable contracts;
- возможность уйти в custom operator screens без борьбы с фреймворком.

---

## Product Positioning

Это не просто UI-kit и не просто генератор CRUD.

Это runtime для Laravel-admin:

- auth для панели;
- layout с header/sidebar/user menu;
- navigation;
- resources;
- tables;
- forms;
- detail pages;
- actions;
- permissions;
- custom pages;
- operator workflows.

Идеальная ценность продукта:

> Установил пакет, описал ресурсы, и получил рабочую админку с хорошим UX и нормальными escape hatches.

---

## Core Principle

Главная модель не `field()` как универсальный API, а отдельные декларативные поверхности:

- `table()`
- `form()`
- `detail()` или `infolist()`
- `actions()`
- `pages()`

Причина простая:

- таблица живёт по одним правилам;
- форма по другим;
- detail page по третьим;
- попытка упаковать всё в один общий `field()` API быстро приводит к перегруженному DSL.

`field()` может существовать как базовый строительный блок, но не как главный публичный интерфейс продукта.

---

## Mental Model

```text
Panel
  -> Resources
  -> Pages
  -> Navigation
  -> Auth
  -> Theme

Resource
  -> table()
  -> form()
  -> detail()
  -> query()
  -> actions()
  -> policies()
  -> pages()

Table
  -> columns()
  -> filters()
  -> scopes()
  -> rowActions()
  -> bulkActions()
  -> pagination()

Form
  -> sections()
  -> tabs()
  -> fields()
  -> rules()
  -> defaults()
  -> mutateData()
  -> afterSave()
```

---

## Public API Shape

Пример желаемого DX:

```php
class UserResource extends Resource
{
    public static string $model = User::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('email')->searchable(),
                BadgeColumn::make('status'),
            ])
            ->filters([
                SelectFilter::make('role'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkDeleteAction::make(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->sections([
                Section::make('Main')->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email(),
                ]),
                Section::make('Access')->schema([
                    Select::make('role'),
                    Toggle::make('is_active'),
                ]),
            ]);
    }
}
```

Ключевой принцип API:

- ресурс описывает структуру;
- библиотека обеспечивает runtime;
- приложение добавляет бизнес-логику;
- фронтенд панели рендерится из общего contract.

---

## What The Package Should Give Out Of The Box

После установки пользователь должен получить:

- admin login flow;
- layout панели;
- dashboard shell;
- sidebar navigation;
- header actions;
- user menu;
- table screens;
- create/edit forms;
- detail pages;
- flash messages;
- modals and confirmations;
- pagination;
- filtering/search/sorting;
- bulk actions;
- permission-aware navigation.

Иными словами, пакет должен покрывать не только CRUD, а полноценный каркас панели.

---

## 80/20 Rule

Хороший admin framework почти всегда живёт по правилу:

- 80% задач решаются декларативно;
- 20% задач решаются через custom pages и расширения.

Если попытаться засунуть абсолютно всё в декларативный DSL, продукт станет тяжёлым и хрупким.

Если не дать декларативный слой, получится просто набор компонентов и абстракций без настоящего DX.

Правильный баланс:

- типовые list/form/detail ресурсы описываются декларативно;
- сложные экраны можно реализовать отдельно, но внутри той же панели и тех же contracts.

---

## Custom Operator Workspaces

Ключевое отличие зрелой админ-библиотеки от простого CRUD-builder:

она должна поддерживать не только ресурсы, но и operator workflows.

Примеры:

- user workspace;
- order processing page;
- finance approval queue;
- partner balance workspace;
- review screen;
- moderation console.

То есть продукт должен уметь не только:

- `index`
- `create`
- `edit`
- `show`

но и:

- `workspace`
- `queue`
- `review`
- `processing`
- `relations hub`

Это очень важно для реальных бизнес-админок.

---

## Backend-Driven Contract

Самая сильная модель для такого продукта:

- backend описывает ресурсы, поля, действия и правила;
- frontend получает унифицированный payload;
- frontend отвечает за consistent rendering.

Преимущества:

- единое поведение во всех ресурсах;
- единый UX;
- меньше ручного frontend-кода в приложении;
- проще поддерживать и расширять;
- можно менять UI-слой без слома доменной модели.

---

## Escape Hatches

Без escape hatches такая библиотека быстро упрётся в потолок.

Обязательно нужны точки выхода:

- кастомный query для ресурса;
- кастомный save pipeline;
- кастомная обработка actions;
- кастомные field renderers;
- кастомные page components;
- кастомный toolbar;
- кастомные relation screens;
- кастомные policies и visibility rules.

Принцип:

> framework должен ускорять типовое, но не мешать сложному.

---

## Layering

Такой продукт лучше мыслить не как один монолитный пакет, а как несколько слоёв:

### 1. Admin Core

Отвечает за:

- resources;
- registry;
- routes;
- actions;
- payload contracts;
- permissions;
- page lifecycle.

### 2. Admin UI

Отвечает за:

- layout;
- navigation;
- table rendering;
- form rendering;
- detail pages;
- overlays;
- notifications;
- visual consistency.

### 3. Application Layer

Остаётся в приложении:

- конкретные resources;
- domain queries;
- бизнес-правила;
- сложные workflows;
- project-specific pages.

---

## What Not To Optimize For

Не стоит строить такой продукт вокруг:

- полного визуального контроля через десятки мелких theme knobs;
- генерации всего подряд кодогенератором;
- магии ради магии;
- попытки закрыть любой use case одним DSL;
- привязки только к простому CRUD.

Реальная ценность здесь не в “автоматически создать формы”, а в том, чтобы стандартизировать повторяющийся admin-runtime.

---

## Success Criteria

Библиотека успешна, если:

- новый ресурс создаётся быстро;
- одинаковые admin-паттерны не переписываются вручную;
- сложные workflow-экраны не ломают архитектуру;
- UX у всей панели консистентный;
- команда не спорит каждый раз заново, как делать table/form/detail shell;
- продукт остаётся расширяемым, а не превращается в тупик.

---

## Concise Conclusion

Такую библиотеку имеет смысл строить.

Но правильная цель не “сделать клон Filament”, а:

- собрать свой admin runtime;
- дать declarative `Resource + table() + form() + detail()`;
- оставить сильные escape hatches;
- поддержать и CRUD, и operator workspaces;
- сделать продукт, который устанавливается как панель, а не как набор разрозненных компонентов.

Если сформулировать в одну строку:

> Нужна не библиотека полей, а библиотека административных сценариев.
