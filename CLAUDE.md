# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Start Development Environment
```bash
composer dev        # Starts Laravel server, queue worker, logs, and Vite dev server
npm run dev        # Just the Vite dev server (if running Laravel separately)
```

### Build & Quality Commands
```bash
npm run build      # Production build
npm run lint       # Lint and auto-fix TypeScript/Vue files
npm run format     # Format code with Prettier
composer test      # Run all PHP tests with Pest
php artisan test --filter TestName  # Run specific test
```

## Architecture Overview

This is a Laravel-Vue.js SPA using Inertia.js as the bridge between backend and frontend.

### Key Architectural Decisions

1. **Inertia.js Pages**: All Vue pages are in `resources/js/pages/` and map directly to Laravel controllers returning Inertia responses. Controllers use `Inertia::render('PageName')` to render Vue components.

2. **Authentication Flow**: Complete auth system with controllers in `app/Http/Controllers/Auth/`. Each auth page (Login, Register, etc.) has corresponding Vue components in `resources/js/pages/auth/`.

3. **Component Library**: Uses shadcn-vue components in `resources/js/components/ui/`. These are headless components with styling via TailwindCSS v4. Component variants are managed with Class Variance Authority (CVA).

4. **TypeScript Types**: Global types in `resources/js/types/` including `index.d.ts` for Inertia page props. Always type Inertia page props using `defineProps<{}>()`.

5. **Testing Strategy**: 
   - PHP tests use Pest framework (not PHPUnit syntax)
   - Tests use in-memory SQLite database
   - Feature tests cover full auth flows
   - Test files follow Laravel conventions in `tests/Feature/` and `tests/Unit/`

### Frontend State Management

- Uses Vue 3 Composition API with TypeScript
- Appearance (dark/light mode) managed via `useAppearance()` composable
- Route helpers available via Ziggy (`route()` function in Vue components)
- Inertia shared data accessible via `usePage().props`

### Database & Migrations

- Default SQLite database at `database/database.sqlite`
- Migrations in `database/migrations/`
- Factories in `database/factories/` for testing
- Run migrations: `php artisan migrate`
- Seed database: `php artisan db:seed`

### Key File Locations

- **Routes**: `routes/web.php` - all application routes
- **Controllers**: `app/Http/Controllers/` - organized by feature
- **Vue Pages**: `resources/js/pages/` - Inertia page components
- **Layouts**: `resources/js/layouts/` - shared layout components
- **UI Components**: `resources/js/components/ui/` - reusable UI components
- **Config**: `config/` - Laravel configuration files
- **Vite Config**: `vite.config.ts` - build configuration