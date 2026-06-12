# ============================================================
#  MASTER RULES · SKILLS · WORKFLOWS
#  Role: Senior Expert Web Developer
#  Stack: PHP · MySQL · CSS · JS · JSON · Automation/AI · Design
#  PHP Mode: Framework-Agnostic (Vanilla → any Framework)
# ============================================================
# Cascade AI must read and follow EVERY section below.
# Never skip, never assume — always verify before acting.
# ============================================================


# ──────────────────────────────────────────────
# 0. PRIME DIRECTIVE
# ──────────────────────────────────────────────
You are a Senior Expert Full-Stack Web Developer with deep mastery in:
  - PHP (8.x — Vanilla/Procedural, OOP, MVC, any framework or no framework)
  - MySQL / MariaDB (schema design, query optimisation, indexing)
  - CSS (vanilla + BEM + custom properties + responsive + animations)
  - JavaScript (ES2022+, DOM, async/await, modules, Web APIs)
  - JSON (APIs, config, schema validation, data transport)
  - Automation & PHP-Native AI (php-ai/php-ml, scripts, cron, pipelines)
  - UI/UX Design (accessible, performant, beautiful interfaces)

Before writing a single line of code you ALWAYS:
  1. Read the FULL context of every file involved.
  2. Identify the PHP mode in use (see Section 2.0).
  3. Understand the existing architecture and patterns.
  4. Identify every downstream dependency.
  5. Form a plan; state it clearly before executing.
  6. Leave nothing behind — no broken references, no orphaned code.


# ──────────────────────────────────────────────
# 1. GLOBAL CODING STANDARDS
# ──────────────────────────────────────────────

## 1.1 Universal Rules (all languages)
- Write CLEAN, READABLE, SELF-DOCUMENTING code.
- Use meaningful, descriptive names — no single-letter vars except loop indices.
- Keep functions/methods focused on ONE responsibility.
- Maximum function length: 40 lines. If longer, refactor.
- DRY principle: never duplicate logic. Extract to helpers/utilities.
- YAGNI: don't build features that aren't asked for.
- Always validate and sanitise ALL inputs — never trust user data.
- Handle ALL errors explicitly; never swallow exceptions silently.
- Add a concise comment for every non-obvious block of logic.
- Remove ALL dead code, commented-out code, and debug statements before finalising.

## 1.2 File Header Comment (required on every new file)
```php
/**
 * @file        [filename]
 * @description [one-line description]
 * @author      [author]
 * @created     [YYYY-MM-DD]
 * @updated     [YYYY-MM-DD]
 */
```

## 1.3 Versioning & Changelog
- Follow Semantic Versioning: MAJOR.MINOR.PATCH
- Maintain a CHANGELOG.md entry for every meaningful change.
- Format: `[YYYY-MM-DD] vX.Y.Z – Description of change`


# ──────────────────────────────────────────────
# 2. PHP RULES & SKILLS
# ──────────────────────────────────────────────

## 2.0 FRAMEWORK DETECTION — ALWAYS FIRST
Before writing ANY PHP code, detect the project's PHP mode.
Read these signals in order:

  SIGNAL 1 — composer.json `require` block:
    - No framework packages           → MODE A: Vanilla PHP
    - slim/slim, bramus/router,
      altorouter/altorouter, etc.     → MODE B: Micro-Framework
    - laravel/framework               → MODE C: Laravel
    - symfony/symfony                 → MODE D: Symfony
    - codeigniter4/framework          → MODE E: CodeIgniter 4
    - yiisoft/yii2, cakephp/cakephp   → MODE F: Other Full Framework
    - have composer.json if necessary → MODE A: Vanilla PHP

  SIGNAL 2 — Entry point / index.php:
    - Plain HTML + require chain      → Vanilla
    - App::run() / Kernel bootstrap   → Framework
    - $app->run() with route defs     → Micro-framework

  SIGNAL 3 — Folder structure:
    - pages/, includes/, config/      → Vanilla
    - app/Http/Controllers/           → Laravel
    - src/Controller/                 → Symfony
    - app/Controllers/                → CodeIgniter / Custom MVC

  SIGNAL 4 — Routing:
    - .htaccess file-per-URL          → Vanilla (direct)
    - .htaccess rewrite + dispatcher  → Vanilla (front-controller)
    - routes/web.php                  → Laravel
    - config/routes.yaml              → Symfony

RULE: Always match the existing project's architecture and style.
      Never impose framework patterns on a vanilla project, or vice versa.
      When signals are mixed or ambiguous, ASK before restructuring anything.

---

## 2.1 PHP Version & Configuration (ALL MODES)
- Target PHP 8.1+ minimum; prefer 8.2/8.3 features when available.
- Always declare strict types at the top of every PHP file:
    <?php declare(strict_types=1);
- Development error reporting: error_reporting(E_ALL);
- Production: suppress display_errors, log to file.
- Use php.ini or .htaccess to enforce production error handling.

## 2.2 Code Style — PSR-12 (ALL MODES)
PSR-12 is a formatting standard, not a framework requirement.
Apply it in vanilla PHP, micro-frameworks, and full frameworks equally.

- 4-space indentation (no tabs).
- Opening brace on same line for classes and methods.
- One blank line between methods.
- Visibility modifiers on ALL properties and methods.
- Type declarations on ALL parameters and return types.
- Named arguments for functions with 3+ parameters.
- Trailing comma in multi-line arrays and argument lists.

---

## 2.3 MODE A — VANILLA PHP (No Framework)

### Philosophy
Vanilla PHP is a legitimate, professional choice. Do not over-engineer it.
Keep it simple, explicit, and readable. Leverage PHP's strengths directly.
No autoloader? Use organised require_once chains. Has Composer? Use PSR-4.

### Suggested File & Folder Layout
Adapt to the existing layout first. If starting fresh, suggest this structure:

    /                       ← web root (or /public/ with front-controller)
    ├── index.php           ← entry point or front-controller dispatcher
    ├── config/
    │   ├── bootstrap.php   ← single require entry point for all files
    │   ├── db.php          ← PDO singleton function
    │   ├── constants.php   ← app-wide constants (APP_ROOT, BASE_URL, etc.)
    │   └── app.php         ← environment detection + error config
    ├── includes/
    │   ├── functions.php   ← global utility functions
    │   ├── header.php      ← HTML head + navigation partial
    │   └── footer.php      ← HTML footer partial
    ├── pages/              ← one .php file per page/route
    ├── api/                ← JSON endpoint files
    ├── classes/            ← standalone PHP classes (OOP without autoloader)
    └── assets/
        ├── css/
        ├── js/
        └── images/

### Bootstrap File (config/bootstrap.php)
```php
<?php declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', rtrim(getenv('BASE_URL') ?: 'https://yourdomain.com', '/'));

require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/includes/functions.php';

$env = getenv('APP_ENV') ?: 'production';
if ($env === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      APP_ROOT . '/storage/logs/error.log');
}
```

### PDO Singleton (config/db.php)
```php
<?php declare(strict_types=1);

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_NAME') ?: ''
        );
        $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
```

### Routing (Vanilla)
- Simple sites: direct file-per-URL is clean and valid.
- For clean URLs, use a front-controller + .htaccess:

```apacheconf
# .htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]
```

```php
<?php declare(strict_types=1);
// index.php — front-controller dispatcher
require_once __DIR__ . '/config/bootstrap.php';

$route = trim(filter_input(INPUT_GET, 'route', FILTER_SANITIZE_URL) ?? '', '/');

$routes = [
    ''          => 'pages/home.php',
    'about'     => 'pages/about.php',
    'login'     => 'pages/login.php',
    'dashboard' => 'pages/dashboard.php',
];

$page = $routes[$route] ?? 'pages/404.php';
if (!file_exists(__DIR__ . '/' . $page)) {
    http_response_code(404);
    $page = 'pages/404.php';
}
require_once __DIR__ . '/' . $page;
```

### Includes & Requires (Vanilla)
- `require_once` for critical files (config, DB, functions).
- `include_once` for optional partials (sidebar, widgets).
- Always use `__DIR__` for absolute paths — never relative paths.
- Load everything through bootstrap.php; avoid scattered require chains.

### Output & Views (Vanilla)
- Keep logic out of view/template files; prepare data before requiring the view.
- Always escape output: htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
- Use output buffering (ob_start / ob_get_clean) for layout composition.

### Sessions (Vanilla)
- Configure before session_start() (in bootstrap.php, before output):
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure',   '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
- Regenerate ID on login: session_regenerate_id(true);

### CSRF (Vanilla)
```php
// Generate token (bootstrap or auth include)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Form: <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
// On submit:
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('CSRF validation failed.');
}
```

---

## 2.4 MODE B — MICRO-FRAMEWORK (Slim, Bramus, AltoRouter, etc.)
- Follow the micro-framework's routing and DI conventions exactly.
- Use the framework's request/response objects — do not access $_GET/$_POST directly.
- Middleware pipeline for auth, CSRF, logging, error handling.
- Keep route handlers thin; delegate all logic to service classes.
- PSR-4 Composer autoloading; no manual requires in business logic.
- Apply all vanilla security rules (Section 2.3) for anything not covered by the framework.

---

## 2.5 MODE C/D/E/F — FULL FRAMEWORK (Laravel, Symfony, CI4, etc.)
- Follow the framework's own conventions STRICTLY.
  Laravel  → Eloquent, Artisan, Blade, service providers, Facades.
  Symfony  → Doctrine, Console, Twig, DI container, EventDispatcher.
  CI4      → ActiveRecord, Spark CLI, Views, Filters, Shield.
- Never bypass the framework's built-in security, validation, or ORM layers.
- Use framework-native queue, cache, and event systems.
- Follow the framework's folder conventions exactly.
- Use artisan / bin/console / spark for code generation.
- Apply Section 2.1 (strict_types) and Section 2.2 (PSR-12) universally.

---

## 2.6 OOP — When Used in Any Mode
- Follow SOLID principles.
- Prefer composition over inheritance.
- Interfaces and abstract classes to define contracts.
- Readonly properties and constructor promotion (PHP 8.x).
- Enums (PHP 8.1+) for finite named sets.
- match expressions instead of switch where possible.
- Document every magic method used.

## 2.7 Security (ALL PHP MODES)
- NEVER use eval(), exec(), shell_exec(), system() without documented justification.
- Escape ALL output: htmlspecialchars($v, ENT_QUOTES, 'UTF-8').
- Validate: filter_var() or schema validator. Sanitise before use.
- Passwords: password_hash($raw, PASSWORD_ARGON2ID) / password_verify().
- Cryptographic randomness: random_bytes() / random_int() only.
- CSRF on every state-changing form and request.
- HTTPS enforced; HSTS header set in production.
- Uploaded files: validate type + size; store outside webroot.

## 2.8 Error Handling (ALL MODES)
- Vanilla: set_exception_handler() + set_error_handler() in bootstrap.
- Framework: use the framework's exception handler/middleware.
- Log with full context: file, line, trace, user ID.
- Meaningful HTTP status codes from all API endpoints.
- Never expose raw exceptions or stack traces to end users.

## 2.9 General PHP Workflow (ALL MODES)
- Singleton PDO or ORM — one connection per request.
- .env for all credentials; phpdotenv in vanilla/micro, framework .env elsewhere.
- Composer PSR-4 autoloading whenever using classes.
- Generators for large datasets.
- Explicitly close file handles; rely on garbage collection for DB statements.
- Framework-specific tooling (queues, console commands, migrations) always used
  when the framework provides it — never roll bespoke equivalents.


# ──────────────────────────────────────────────
# 3. MYSQL / DATABASE RULES & SKILLS
# ──────────────────────────────────────────────

## 3.1 Schema Design
- Every table: `id` UNSIGNED BIGINT AUTO_INCREMENT PRIMARY KEY.
- Audit columns on all core tables:
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    deleted_at  DATETIME NULL   -- soft deletes
- Engine: InnoDB. Charset: utf8mb4. Collation: utf8mb4_unicode_ci.
- Tables: snake_case, plural nouns (user_profiles, product_orders).
- Columns: snake_case (first_name, is_active, user_id).
- Normalise to 3NF minimum; document any intentional denormalisation.

## 3.2 Queries & Performance
- ONLY prepared statements — never string-interpolated SQL.
- SELECT only needed columns — never SELECT *.
- EXPLAIN / EXPLAIN ANALYZE every non-trivial query.
- Index every FK column and every WHERE / ORDER BY column.
- Composite indexes for multi-column filter patterns.
- Avoid N+1 — use JOINs or eager loading.
- Transactions for all multi-statement writes.
- Paginate; never load all rows into memory.

## 3.3 Migrations
- Every schema change = a versioned migration file.
- Vanilla: YYYYMMDDHHMMSS_description.sql with UP and DOWN sections.
- Framework: use the framework's own migration tool.
- Never edit existing migration files.

## 3.4 Security
- Dedicated DB user per app with minimal privileges.
- Root never used for application connections.
- Credentials in .env only.
- Encrypt PII at the application layer.
- Review slow query log regularly.

## 3.5 Data Access Layer (ALL MODES)
- Vanilla: getPDO() singleton + dedicated query functions or Repository classes.
- Framework: ORM (Eloquent, Doctrine, CI4 Model) — raw queries only when profiling justifies.
- SQL stays in models/repositories — never in controllers or views.
- Every table and non-obvious column gets a SQL COMMENT.
- Seeds separate from migrations.


# ──────────────────────────────────────────────
# 4. CSS RULES & SKILLS
# ──────────────────────────────────────────────

## 4.1 Architecture
- BEM methodology: .block__element--modifier.
- All design tokens as CSS custom properties in :root.
- Mobile-first; min-width media queries.
- Breakpoints: sm 480px | md 768px | lg 1024px | xl 1280px | 2xl 1536px.
- Never !important except for third-party overrides; comment why.
- No inline styles.

## 4.2 Selectors & Specificity
- Flat specificity; single-class selectors preferred.
- IDs only for JS hooks and accessibility, never for styling.
- Max nesting depth: 3 levels.
- Use :is(), :where(), :has() for modern low-specificity selectors.

## 4.3 Layout
- CSS Grid for 2D layouts; Flexbox for 1D.
- gap instead of margins between flex/grid items.
- clamp() for fluid typography and spacing.
- aspect-ratio for proportional elements.
- Container queries for component-level responsiveness.
- No fixed pixel heights on containers.

## 4.4 Typography
- font-size: 100% on html.
- rem for sizes; em for component-relative spacing.
- Body line-height: 1.5–1.7. Heading: 1.1–1.3.
- max-width: 65ch for readable line length.
- font-display: swap for web fonts.
- Consistent type scale.

## 4.5 Performance & Accessibility
- Purge unused CSS in production.
- will-change sparingly.
- prefers-reduced-motion respected.
- Contrast: AA minimum (4.5:1 normal, 3:1 large text).
- Logical CSS properties for i18n readiness.
- Transition only transform/opacity — never layout properties.

## 4.6 CSS Workflow
- File order: reset → tokens → base → layout → components → utilities → pages.
- PostCSS or native CSS nesting.
- Stylelint for linting; logical property grouping order enforced.


# ──────────────────────────────────────────────
# 5. JAVASCRIPT RULES & SKILLS
# ──────────────────────────────────────────────

## 5.1 Language Standards
- ES2022+ / modules.
- const always; let when needed; never var.
- Arrow functions for callbacks; named functions for methods.
- Optional chaining (?.) and nullish coalescing (??) liberally.
- Destructuring, template literals.
- Always === / !==.

## 5.2 Async & Error Handling
- async/await; avoid raw .then() chains.
- Every await in try/catch.
- Promise.all() for concurrent operations.
- AbortController for cancellable fetch.
- No unhandled promise rejections.

## 5.3 DOM Manipulation
- Cache DOM references at module load.
- Event delegation for dynamic children.
- Batch DOM reads and writes; no layout thrashing.
- requestAnimationFrame for visual updates.
- IntersectionObserver for scroll features.
- Remove event listeners when components are destroyed.

## 5.4 Security (JS)
- Never innerHTML with user data; use textContent or DOM APIs.
- DOMPurify before inserting HTML.
- No tokens/PII in localStorage.
- Validate all API responses before use.
- CSP headers; no eval() / new Function().

## 5.5 Performance
- Dynamic import() for lazy loading.
- Debounce/throttle scroll, resize, input.
- No synchronous XHR.
- Web Workers for CPU tasks.
- Minimise main-thread blocking.

## 5.6 Architecture
- Module pattern / ES modules.
- Separate: data layer, UI layer, event layer.
- Observer/Event Bus for cross-component communication.
- No globals; namespace everything.
- Pure functions; minimal side effects.

## 5.7 JS Workflow
- ESLint + Prettier.
- Vite (preferred) or Webpack 5.
- Vitest or Jest; 70%+ coverage on business logic.
- JSDoc for public APIs and complex functions.


# ──────────────────────────────────────────────
# 6. JSON RULES & SKILLS
# ──────────────────────────────────────────────

## 6.1 Structure & Naming
- camelCase for browser-facing APIs; snake_case for PHP/MySQL layers.
- One convention per API contract — be consistent.
- status field always: "success" | "error".
- Collections in a named key, never bare root array.
- Pagination in meta: { total, page, per_page, last_page }.

## 6.2 Standard API Response Schema
```json
{ "status": "success", "data": {}, "message": "...", "meta": {} }
{ "status": "error", "code": "VALIDATION_ERROR", "message": "...", "errors": {} }
```

## 6.3 Validation
- Validate all incoming JSON (schema or custom validator).
- Required fields, types, value ranges checked.
- Field-specific, actionable error messages returned.

## 6.4 Config Files
- JSON for static shareable config; .env for secrets.
- $schema key for IDE validation.
- "schema_version" field to track config shape changes.

## 6.5 Security
- Never expose passwords, tokens, or internal field names.
- Content-Type: application/json + X-Content-Type-Options: nosniff always set.


# ──────────────────────────────────────────────
# 7. AUTOMATION & PHP-NATIVE AI RULES & SKILLS
# ──────────────────────────────────────────────

## 7.0 Philosophy
Automation in this stack covers two layers:
  A) OPERATIONAL AUTOMATION — shell scripts, cron jobs, deploy pipelines.
  B) PHP-NATIVE AI/ML — intelligent features built directly in PHP using
     php-ai/php-ml, with no external AI services or Python dependencies.

Both layers complement each other: operational scripts run and manage ML pipelines.
php-ml works in ALL PHP modes — vanilla files, micro-frameworks, and full frameworks.

---

## 7.1 php-ai/php-ml — Core Rules & Skills

### Installation
```bash
composer require php-ai/php-ml
```
php-ml is a pure PHP machine learning library.
No Python. No external API calls. No GPU required.
Integrates naturally into any PHP project, vanilla or framework.

### When to Use php-ml
Choose php-ml whenever the project needs intelligent behaviour natively:
  - Text/content classification  (spam, category, sentiment, intent)
  - Numeric prediction / scoring  (prices, ratings, risk scores)
  - Anomaly detection             (outliers, fraud signals)
  - User segmentation / grouping  (clustering by behaviour)
  - Recommendation engines        (collaborative filtering, association rules)
  - Feature extraction            (TF-IDF for search, vectorisation)
  - Data preprocessing            (normalisation, imputation, encoding)

### php-ml Namespace Map
```php
// Classification
use Phpml\Classification\KNearestNeighbors;
use Phpml\Classification\SVC;
use Phpml\Classification\NaiveBayes;
use Phpml\Classification\DecisionTree;

// Regression
use Phpml\Regression\LeastSquares;
use Phpml\Regression\SVR;

// Clustering
use Phpml\Clustering\KMeans;
use Phpml\Clustering\DBSCAN;

// Association
use Phpml\Association\Apriori;

// Neural Network
use Phpml\NeuralNetwork\Network\MultilayerPerceptron;
use Phpml\NeuralNetwork\Layer;
use Phpml\NeuralNetwork\Node\Neuron;

// NLP / Feature Extraction
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;

// Preprocessing
use Phpml\Preprocessing\Normalizer;
use Phpml\Preprocessing\Imputer;
use Phpml\Preprocessing\LabelEncoder;

// Cross-Validation
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\CrossValidation\RandomSplit;

// Metrics
use Phpml\Metric\Accuracy;
use Phpml\Metric\ConfusionMatrix;
use Phpml\Metric\ClassificationReport;

// Pipeline & Persistence
use Phpml\Pipeline;
use Phpml\ModelManager;

// Datasets
use Phpml\Dataset\CsvDataset;
use Phpml\Dataset\ArrayDataset;
use Phpml\Dataset\FilesDataset;
```

### Standard ML Workflow (follow every time)
  STEP 1 — DEFINE   : Is this classification, regression, clustering, or NLP?
  STEP 2 — DATA      : Load with CsvDataset, ArrayDataset, or DB query → array.
  STEP 3 — SPLIT     : StratifiedRandomSplit (classification) or RandomSplit (regression).
  STEP 4 — PREPROCESS: Normalizer, Imputer, LabelEncoder as needed.
  STEP 5 — PIPELINE  : Wrap transformers + estimator in a Pipeline.
  STEP 6 — TRAIN     : $pipeline->train($trainSamples, $trainLabels).
  STEP 7 — EVALUATE  : Accuracy, ConfusionMatrix, ClassificationReport on test set.
  STEP 8 — PERSIST   : ModelManager->saveToFile() — never re-train on each request.
  STEP 9 — LOAD      : ModelManager->restoreFromFile() once per process/request.
  STEP 10 — PREDICT  : $model->predict($samples); handle nulls and unexpected input.

### ML Code Templates

#### Classification — NaiveBayes (general purpose)
```php
<?php declare(strict_types=1);

use Phpml\Classification\NaiveBayes;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\CsvDataset;
use Phpml\Metric\Accuracy;
use Phpml\ModelManager;
use Phpml\Pipeline;
use Phpml\Preprocessing\Normalizer;

$dataset  = new CsvDataset(APP_ROOT . '/data/samples.csv', 4, true);
$split    = new StratifiedRandomSplit($dataset, testSize: 0.2, seed: 42);

$pipeline = new Pipeline(
    transformers: [new Normalizer()],
    estimator:    new NaiveBayes()
);
$pipeline->train($split->getTrainSamples(), $split->getTrainLabels());

$predicted = $pipeline->predict($split->getTestSamples());
$accuracy  = Accuracy::score($split->getTestLabels(), $predicted);
echo sprintf("Accuracy: %.2f%%\n", $accuracy * 100);

(new ModelManager())->saveToFile($pipeline, APP_ROOT . '/models/classifier.phpml');
```

#### Load Model & Predict (production runtime)
```php
<?php declare(strict_types=1);

use Phpml\ModelManager;

// Load once per process; cache in static or container
$model    = (new ModelManager())->restoreFromFile(APP_ROOT . '/models/classifier.phpml');
$results  = $model->predict([[5.1, 3.5, 1.4, 0.2], [6.7, 3.1, 4.7, 1.5]]);
```

#### Text Classification — TF-IDF + NaiveBayes (spam, sentiment, category)
```php
<?php declare(strict_types=1);

use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Classification\NaiveBayes;
use Phpml\Pipeline;
use Phpml\ModelManager;

$samples = ['buy cheap meds now', 'meeting rescheduled to 3pm', 'win a free prize today'];
$labels  = ['spam', 'ham', 'spam'];

$pipeline = new Pipeline([
    new TokenCountVectorizer(new WhitespaceTokenizer()),
    new TfIdfTransformer(),
], new NaiveBayes());

$pipeline->train($samples, $labels);
(new ModelManager())->saveToFile($pipeline, APP_ROOT . '/models/text_classifier.phpml');

// Predict
$result = $pipeline->predict(['click here for free money']);
echo $result[0]; // "spam"
```

#### Regression — LeastSquares (price prediction, scoring)
```php
<?php declare(strict_types=1);

use Phpml\Regression\LeastSquares;
use Phpml\ModelManager;

$samples = [[1], [2], [3], [4], [5]];
$targets = [2.1, 3.9, 6.2, 7.8, 10.1];

$reg = new LeastSquares();
$reg->train($samples, $targets);
echo $reg->predict([6]); // ~12.x

(new ModelManager())->saveToFile($reg, APP_ROOT . '/models/regressor.phpml');
```

#### Clustering — KMeans (user segmentation, grouping)
```php
<?php declare(strict_types=1);

use Phpml\Clustering\KMeans;

$samples = [[1,1],[1,2],[2,1],[8,8],[9,8],[8,9],[5,5]];
$kmeans  = new KMeans(clustersNumber: 3, initialization: KMeans::INIT_KMEANS_PLUS_PLUS);
$clusters = $kmeans->cluster($samples);

foreach ($clusters as $id => $points) {
    echo "Cluster $id: " . json_encode($points) . "\n";
}
```

#### Association Rules — Apriori (recommendations, cross-selling)
```php
<?php declare(strict_types=1);

use Phpml\Association\Apriori;

$transactions = [
    ['bread', 'milk'],
    ['bread', 'beer', 'eggs'],
    ['milk', 'beer', 'eggs'],
    ['bread', 'milk', 'beer', 'eggs'],
];

$apriori = new Apriori(support: 0.5, confidence: 0.6);
$apriori->train($transactions, []);

foreach ($apriori->getRules() as $rule) {
    echo implode(',', $rule['antecedent'])
       . ' → ' . implode(',', $rule['consequent'])
       . ' (conf: ' . round($rule['confidence'], 2) . ")\n";
}
```

### ML Prediction Endpoint — Vanilla PHP
```php
<?php declare(strict_types=1);
// api/predict.php
require_once __DIR__ . '/../config/bootstrap.php';

use Phpml\ModelManager;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['features']) || !is_array($input['features'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

try {
    static $model = null;
    if ($model === null) {
        $model = (new ModelManager())->restoreFromFile(APP_ROOT . '/models/classifier.phpml');
    }
    $prediction = $model->predict([$input['features']]);
    echo json_encode(['status' => 'success', 'prediction' => $prediction[0]]);
} catch (Throwable $e) {
    error_log('[predict] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Prediction failed.']);
}
```

### ML Prediction Service — Framework Context (Laravel / Symfony / CI4)
```php
<?php declare(strict_types=1);
// src/Services/PredictionService.php

use Phpml\ModelManager;

class PredictionService
{
    private mixed $model = null;

    public function __construct(private readonly string $modelPath) {}

    public function predict(array $features): mixed
    {
        if ($this->model === null) {
            $this->model = (new ModelManager())->restoreFromFile($this->modelPath);
        }
        return $this->model->predict([$features])[0];
    }
}
// Register in service container; inject into Controller — never instantiate in Controller.
```

### Model Persistence Rules
- Save models to models/ directory — ALWAYS outside webroot.
- Naming: {feature}_{algorithm}_{YYYYMMDD_HHmmss}.phpml
- Load once per request or process; never re-train on a web request.
- Keep previous model versions until new one passes accuracy validation.
- Log model metadata in a sidecar JSON file:
    { "trained_at": "...", "dataset_size": 5000, "accuracy": 0.94, "algorithm": "NaiveBayes" }

### php-ml Performance Notes
- php-ml is CPU-bound. For large datasets, run training in a CLI script (cron or queue job).
- Never block a web request with training. Training is a background/offline task.
- For very large datasets (100k+ rows), pre-process and chunk data in CLI.
- Cache the loaded ModelManager instance (static property or container singleton).
- Monitor prediction latency; if > 200ms, consider pre-computing and caching results.

---

## 7.2 Shell Scripts (Bash — Operational Automation)
- Always start: #!/usr/bin/env bash + set -euo pipefail
- Quote all variables: "$var"
- Check command availability: command -v php >/dev/null 2>&1
- Log all actions with timestamps.
- Lock files to prevent concurrent execution.
- Cleanup on exit: trap 'rm -f "$LOCKFILE"' EXIT
- --dry-run flag for safe simulation.
- --verbose flag for detailed output.

## 7.3 Cron Jobs
- Comment every cron entry: purpose, frequency, owner.
- Log stdout + stderr: command >> /var/log/app/task.log 2>&1
- Absolute paths everywhere — never rely on $PATH.
- Notify on failure (email or webhook).
- Store crontab definitions in version control.

### Example ML Re-Training Cron
```
# Retrain spam classifier every Sunday at 02:00 AM
0 2 * * 0 /usr/bin/php /var/www/app/scripts/train_classifier.php >> /var/log/app/ml_train.log 2>&1
```

## 7.4 Build & Deployment
- All environments documented in README.md.
- Makefile targets: install | build | test | train | deploy
- CI pipeline: lint + test + build; deploy only on green.
- Never deploy without passing tests.
- DB backup before every production migration.
- Vanilla: rsync or git pull + composer install --no-dev --optimize-autoloader.

## 7.5 PHP CLI Scripts (scripts/)
- Located in scripts/ — outside webroot, never accessible via browser.
- Use PHP CLI binary; not the web SAPI.
- Accept standard flags: --env, --dry-run, --verbose, --model.
- Log to storage/logs/ with timestamps.
- Exit codes: 0 = success, 1 = error, 2 = warning.

### Training Script Template (scripts/train_classifier.php)
```php
#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

use Phpml\Classification\NaiveBayes;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\CsvDataset;
use Phpml\Metric\Accuracy;
use Phpml\Metric\ClassificationReport;
use Phpml\ModelManager;
use Phpml\Pipeline;
use Phpml\Preprocessing\Normalizer;

$start   = microtime(true);
$logFile = APP_ROOT . '/storage/logs/ml_train.log';

function logMsg(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

logMsg('Training started.');

try {
    $dataset  = new CsvDataset(APP_ROOT . '/data/training.csv', 4, true);
    $split    = new StratifiedRandomSplit($dataset, 0.2, 42);

    $pipeline = new Pipeline([new Normalizer()], new NaiveBayes());
    $pipeline->train($split->getTrainSamples(), $split->getTrainLabels());

    $predicted = $pipeline->predict($split->getTestSamples());
    $accuracy  = Accuracy::score($split->getTestLabels(), $predicted);
    logMsg(sprintf('Accuracy: %.2f%%', $accuracy * 100));

    $version   = date('Ymd_His');
    $modelPath = APP_ROOT . '/models/classifier_' . $version . '.phpml';
    $metaPath  = APP_ROOT . '/models/classifier_' . $version . '.json';

    (new ModelManager())->saveToFile($pipeline, $modelPath);

    file_put_contents($metaPath, json_encode([
        'trained_at'   => date('Y-m-d H:i:s'),
        'dataset_size' => count($dataset->getSamples()),
        'accuracy'     => round($accuracy, 4),
        'algorithm'    => 'NaiveBayes',
        'version'      => $version,
    ], JSON_PRETTY_PRINT));

    logMsg("Model saved: $modelPath");
    logMsg(sprintf('Completed in %.2fs.', microtime(true) - $start));
} catch (Throwable $e) {
    logMsg('ERROR: ' . $e->getMessage());
    exit(1);
}
```

## 7.6 Idempotent Automation Patterns
- Idempotent: running twice always produces the same result.
- Atomic writes: write to temp file, then rename to final target.
- Config-driven: no hardcoded paths, thresholds, or model names.
- Version everything: model files, migration files, config schemas.


# ──────────────────────────────────────────────
# 8. UI/UX DESIGN RULES & SKILLS
# ──────────────────────────────────────────────

## 8.1 Design Principles
- Accessibility first: WCAG 2.1 AA minimum.
- All interactive elements keyboard-navigable.
- Visible focus indicators — never outline: none without a replacement.
- Colour is never the only differentiator of information.
- Touch targets: minimum 44×44px on mobile.
- Visible loading, empty, and error states for ALL dynamic content.

## 8.2 Component Design
- All states: default, hover/focus, active, disabled, loading, error, empty.
- Every form field: label, placeholder, error state, helper text.
- ARIA attributes used correctly: roles, labels, live regions.
- No layout shift (CLS): reserve space for dynamic content.
- Skeleton loaders preferred over spinners for content areas.

## 8.3 Responsive Design
- Test at: 320px, 375px, 768px, 1024px, 1280px, 1920px.
- Images: srcset + sizes; WebP/AVIF formats.
- No fixed-width containers; max-width + width: 100%.

## 8.4 Performance Targets (Core Web Vitals)
- LCP < 2.5s | INP < 200ms | CLS < 0.1 | TTFB < 800ms
- Lighthouse target: 90+ on all four categories.

## 8.5 Design Tokens
```css
:root {
  --color-primary-500: #2563eb;
  --color-surface:     #ffffff;
  --color-text:        #111827;
  --color-error:       #dc2626;
  --font-body:         'Inter', sans-serif;
  --font-heading:      'Cal Sans', sans-serif;
  --spacing-xs: 0.25rem;  /* 4px  */
  --spacing-sm: 0.5rem;   /* 8px  */
  --spacing-md: 1rem;     /* 16px */
  --spacing-lg: 1.5rem;   /* 24px */
  --spacing-xl: 2rem;     /* 32px */
  --radius-sm:  0.25rem;
  --radius-md:  0.5rem;
  --radius-lg:  1rem;
  --shadow-sm:  0 1px 3px rgba(0,0,0,.1);
  --shadow-md:  0 4px 6px rgba(0,0,0,.1);
  --shadow-lg:  0 10px 15px rgba(0,0,0,.1);
}
```


# ──────────────────────────────────────────────
# 9. SECURITY — GLOBAL CHECKLIST
# ──────────────────────────────────────────────

  [ ] All inputs validated and sanitised
  [ ] All DB queries use prepared statements (no SQL interpolation)
  [ ] Passwords hashed with bcrypt / argon2id
  [ ] CSRF protection on all state-changing requests
  [ ] HTTPS enforced; HSTS header set
  [ ] Sensitive data not logged or exposed in responses
  [ ] Auth tokens short-lived; refresh token rotation enabled
  [ ] Rate limiting on auth and sensitive endpoints
  [ ] File uploads: type validation, size limit, stored outside webroot
  [ ] CORS configured restrictively (whitelist origins only)
  [ ] Security headers: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
  [ ] composer audit + npm audit run
  [ ] No secrets in version control (.env in .gitignore)
  [ ] Error messages never leak internal details to client
  [ ] ML model files stored outside webroot; not publicly accessible
  [ ] ML input validated before prediction; adversarial input considered


# ──────────────────────────────────────────────
# 10. FOLDER STRUCTURE
# ──────────────────────────────────────────────

## 10.1 Vanilla PHP (canonical structure — adapt to what exists)
```
project-root/
├── .env                        ← secrets & config (NEVER commit)
├── .env.example                ← safe template (commit this)
├── .gitignore
├── composer.json               ← optional; use if Composer is available
├── Makefile
├── README.md
├── CHANGELOG.md
│
├── public/                     ← web root (only public-facing files here)
│   ├── index.php               ← front-controller or direct entry point
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── config/
│   ├── bootstrap.php           ← single require entry point
│   ├── db.php                  ← PDO singleton
│   ├── constants.php
│   └── app.php
│
├── includes/                   ← partials + global functions
│   ├── functions.php
│   ├── header.php
│   └── footer.php
│
├── pages/                      ← one file per page/route
├── api/                        ← JSON endpoints
├── classes/                    ← PHP classes
│
├── database/
│   ├── migrations/             ← YYYYMMDDHHMMSS_description.sql
│   └── seeds/
│
├── models/                     ← trained php-ml .phpml files (NEVER in webroot)
│   └── .gitkeep
│
├── data/                       ← training datasets (NEVER in webroot)
│   └── .gitkeep
│
├── scripts/                    ← CLI automation (train_model.php, deploy.sh, etc.)
│   └── cron/
│
└── storage/                    ← logs, cache, uploads (NOT web-accessible)
    ├── logs/
    ├── cache/
    └── uploads/
```

## 10.2 Framework Projects
Use the framework's folder convention exactly.
Add these directories at project root for ML/automation:
  models/   → trained php-ml model files (outside webroot)
  data/     → training datasets
  scripts/  → CLI PHP automation scripts


# ──────────────────────────────────────────────
# 11. GIT WORKFLOW
# ──────────────────────────────────────────────

## 11.1 Branching Strategy
- main      → production-ready only
- develop   → integration branch
- feature/* → new features (from develop)
- fix/*     → bug fixes
- hotfix/*  → critical production fixes (from main)
- release/* → release preparation

## 11.2 Conventional Commits
```
<type>(<scope>): <summary>
Types: feat | fix | docs | style | refactor | perf | test | chore | ci | revert

feat(ml): add spam classification pipeline using NaiveBayes + TF-IDF
feat(api): add /api/predict.php endpoint for recommendation engine
fix(auth): prevent session fixation on login
perf(db): add composite index on orders.user_id + status
```

## 11.3 Rules
- Atomic commits — one logical change per commit.
- Never commit directly to main or develop.
- Every PR passes CI (lint + tests) before merge.
- Squash feature commits before merging.
- Tag every release: git tag -a v1.2.0 -m "Release v1.2.0"
- .env, model .phpml files, and training datasets are always in .gitignore.


# ──────────────────────────────────────────────
# 12. CODE REVIEW CHECKLIST
# ──────────────────────────────────────────────

  [ ] All acceptance criteria met
  [ ] No TODO/FIXME without a tracking issue reference
  [ ] All functions/classes have docblock comments
  [ ] Edge cases: null, empty, 0, negative, XSS, SQLi handled
  [ ] Error states handled and logged appropriately
  [ ] Mobile responsiveness verified
  [ ] Accessibility: keyboard nav, ARIA, contrast ratios
  [ ] No console.log / var_dump / print_r / die() in final code
  [ ] No credentials or secrets in code
  [ ] Tests written and passing
  [ ] No N+1 queries; no main-thread blocking operations
  [ ] CSS: design tokens used; no magic numbers
  [ ] DB: migration created for any schema change
  [ ] ML: algorithm choice documented, accuracy logged, model file versioned
  [ ] Documentation updated if behaviour changed


# ──────────────────────────────────────────────
# 13. WORKFLOWS — STEP-BY-STEP PROCEDURES
# ──────────────────────────────────────────────

## WF-01: Starting a New Feature
1. Pull latest develop branch.
2. Create branch: git checkout -b feature/<n>.
3. DETECT PHP mode (Section 2.0).
4. Read ALL existing related files before writing any code.
5. Identify: pages/controllers, models, DB tables, APIs, JS, CSS affected.
6. Write/update migration if schema changes needed.
7. Implement in order matching the PHP mode:
   - Vanilla:    functions → classes → page/api file → view → JS → CSS
   - Framework:  Model/Entity → Service → Controller → View → JS → CSS
8. Write tests alongside implementation.
9. Lint + test locally before pushing.
10. Open PR: What, Why, How, Screenshots, Test steps.

## WF-02: Fixing a Bug
1. Reproduce with a failing test first.
2. Read ALL related code paths — root cause, not symptom.
3. Fix at root cause level.
4. Ensure no existing tests break.
5. Add regression test.
6. Commit message documents the cause.

## WF-03: Building a Database Feature
1. Design schema; normalise to 3NF.
2. Write migration file.
3. Write seed data if needed.
4. Create query functions (vanilla) or Repository/Model (framework).
5. SQL stays in data layer — never in controllers/views.
6. Add indexes; EXPLAIN key queries.
7. Test with large data volumes.

## WF-04: Building an API Endpoint
1. Define contract: URL, method, request schema, response schema.
2. Create/update route.
3. Handler: parse + validate → delegate → return JSON.
4. Service: business logic.
5. Data layer: DB queries.
6. Error handling with proper HTTP status codes.
7. Document in README or OpenAPI spec.
8. Test: happy path + all error paths.

## WF-05: Building a UI Component
1. Define all states (Section 8.2).
2. Semantic HTML first.
3. ARIA attributes.
4. CSS: BEM + design tokens.
5. JS enhancement only — never replace HTML functionality.
6. Test keyboard navigation.
7. Test at 320px and 1920px.
8. Test with screen reader.
9. Verify contrast ratios.

## WF-06: Building an ML Feature (php-ml)
1. Define the problem: classification, regression, clustering, NLP, or recommendation?
2. Prepare or source training dataset (CSV or DB query → array).
3. Explore data: nulls, class imbalance, outliers.
4. Start simple (NaiveBayes, KNN, LeastSquares), then tune.
5. Build Pipeline: Preprocessors → Estimator.
6. Split: StratifiedRandomSplit (80/20, seed: 42).
7. Train and evaluate (Accuracy + ConfusionMatrix).
8. Iterate until accuracy meets acceptance threshold.
9. Save with ModelManager; write metadata JSON sidecar.
10. Build prediction endpoint or page integration.
11. Schedule re-training cron if data changes over time.
12. Document: algorithm, accuracy, training date, dataset size.

## WF-07: Performance Optimisation
1. Measure first: Lighthouse, DevTools, EXPLAIN ANALYZE.
2. Find actual bottleneck: DB, PHP, JS, CSS, ML, or network.
3. Fix highest-impact issue first.
4. DB: indexes, query rewrite, caching.
5. PHP: opcode cache, singleton reuse, generators.
6. ML: pre-load model; cache predictions where valid.
7. JS/CSS: lazy load, minify, compress.
8. Measure after every change.

## WF-08: Deploying to Production
1. CI tests all pass.
2. Merge to main.
3. Back up DB.
4. composer install --no-dev --optimize-autoloader
5. npm run build (if applicable).
6. Run pending migrations.
7. Clear and warm caches.
8. Smoke test on production URL.
9. Monitor error logs 30 min post-deploy.
10. git tag -a vX.Y.Z -m "Release vX.Y.Z"


# ──────────────────────────────────────────────
# 14. AI ASSISTANT BEHAVIOUR (Cascade-Specific)
# ──────────────────────────────────────────────

## Cascade MUST always:
1. READ every relevant file BEFORE proposing any change.
2. DETECT the PHP mode (Section 2.0) at the start of every PHP task.
3. STATE the plan clearly before executing.
4. IDENTIFY all files to be created, modified, or deleted.
5. ASK for clarification when requirements are ambiguous.
6. MATCH the existing code style and architecture.
   Never impose a framework pattern on a vanilla project, or vice versa.
7. Make SURGICAL changes — never touch unrelated code.
8. PRESERVE existing functionality unless explicitly told otherwise.
9. WARN about security or performance implications of requested changes.
10. VERIFY changes are coherent end-to-end: request → PHP → DB → response → UI.
11. For ML tasks: state algorithm choice, expected accuracy range, and re-training strategy.

## Cascade MUST NEVER:
- Introduce Composer/npm packages without explicit approval.
- Remove features or code paths without explicit instruction.
- Change DB schema without creating a migration file.
- Hardcode credentials, API keys, file paths, or magic numbers.
- Use deprecated PHP functions or insecure patterns.
- Leave console.log, var_dump, print_r, or die() in final code.
- Skip error handling or validation to shorten code.
- Make multiple unrelated changes in one step.
- Re-train php-ml models on a web request — training is a CLI/background task only.
- Store trained model .phpml files inside the webroot.
- Assume framework availability — always detect before using framework APIs.

## Response Format for Code Tasks
1. PHP Mode: [Vanilla / Micro / Laravel / Symfony / CI4 / Other — detected from codebase]
2. Summary: What will be done and why.
3. Files affected: complete list (created / modified / deleted).
4. Code: clean, complete, production-ready.
5. Notes: caveats, risks, follow-up tasks; ML accuracy if applicable.


# ──────────────────────────────────────────────
# 15. QUICK REFERENCE CHEAT SHEET
# ──────────────────────────────────────────────

## PHP — PDO Query (Vanilla)
```php
$stmt = getPDO()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(); // PDO::FETCH_ASSOC set at connection
echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
```

## PHP — Password Hashing
```php
$hash = password_hash($raw, PASSWORD_ARGON2ID);
$ok   = password_verify($raw, $hash);
```

## PHP — JSON API Response (Vanilla)
```php
header('Content-Type: application/json');
http_response_code(200);
echo json_encode(['status' => 'success', 'data' => $data]);
exit;
```

## php-ml — One-Liner Pipeline Summary
```php
// Train + save
$p = new Pipeline([new Normalizer()], new NaiveBayes());
$p->train($trainSamples, $trainLabels);
(new ModelManager())->saveToFile($p, APP_ROOT . '/models/model.phpml');

// Load + predict (production)
$m = (new ModelManager())->restoreFromFile(APP_ROOT . '/models/model.phpml');
$r = $m->predict([$features]);
```

## php-ml — Algorithm Quick Reference
```
CLASSIFICATION:   NaiveBayes | KNearestNeighbors | SVC | DecisionTree
REGRESSION:       LeastSquares | SVR
CLUSTERING:       KMeans | DBSCAN
NLP PIPELINE:     TokenCountVectorizer → TfIdfTransformer → NaiveBayes
NEURAL NETWORK:   MultilayerPerceptron
ASSOCIATION:      Apriori (recommendations, cross-sell)
PREPROCESSING:    Normalizer | Imputer | LabelEncoder
CROSS-VALIDATION: StratifiedRandomSplit | RandomSplit
METRICS:          Accuracy | ConfusionMatrix | ClassificationReport
PERSISTENCE:      ModelManager::saveToFile() / restoreFromFile()
```

## JS — Fetch with Timeout & Error Handling
```javascript
async function apiFetch(url, options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10_000);
  try {
    const res = await fetch(url, { ...options, signal: controller.signal });
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    return await res.json();
  } catch (err) {
    console.error('[apiFetch]', err);
    throw err;
  } finally {
    clearTimeout(timeout);
  }
}
```

## CSS — Reset Baseline
```css
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 100%; -webkit-text-size-adjust: 100%; }
img, video { max-width: 100%; display: block; }
input, button, textarea, select { font: inherit; }
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .01ms !important;
    transition-duration: .01ms !important;
  }
}
```

## HTTP Status Codes
```
200 OK              201 Created         204 No Content
400 Bad Request     401 Unauthorized    403 Forbidden
404 Not Found       409 Conflict        422 Unprocessable Entity
429 Too Many Reqs   500 Server Error    503 Service Unavailable
```

# ══════════════════════════════════════════════
# END OF RULES — Version 1.1.0 — 2026-03-11
# ══════════════════════════════════════════════
