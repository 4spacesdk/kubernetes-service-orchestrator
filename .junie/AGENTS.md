# Project Guidelines - Kubernetes Service Orchestrator (KSO)

This document serves as an in-depth guide for AI agents (like Junie) working on this project. It describes the architecture, technologies, and standards to be followed.

## 1. Project Overview
Kubernetes Service Orchestrator (KSO) is a platform designed to automate the deployment and management of services in Kubernetes clusters. 
The system allows for the configuration of complex deployments via a user-friendly interface and handles interaction with the Kubernetes API behind the scenes.

## 2. Technology Stack
- **Backend**: CodeIgniter 4 (PHP 8.1+)
- **Frontend**: Vue.js 3 (TypeScript, Vite, Tailwind CSS)
- **Database**: MySQL / MariaDB
- **Messaging**: ZeroMQ (ZMQ) for event-driven communication
- **Infrastructure**: Docker, Kubernetes, Helm, Google Cloud Build

## 3. Project Structure

### Backend (`/ci4`)
- `app/Controllers`: Handles API endpoints. Most return JSON to the Vue frontend.
- `app/Models`: Contains database logic and entities. We use CodeIgniter's Model system extensively.
- `app/Libraries/Kubernetes`: The critical part of the system. Contains classes to interact with Kubernetes resources (e.g., `KubeHelper`, `KubeCertificate`) and Custom Resource Definitions (CRDs) like `K8sGateway` and `K8sHttpRoute`.
- `app/Database/Migrations`: All database changes **must** happen via migrations.

### Frontend (`/vue`)
- `src/components`: Reusable UI components.
- `src/services`: Handles API calls to the backend.
- `src/router`: Vue Router configuration.
- `src/core`: Core logic and state management.

### Other Components
- `zmq-server` & `zmq-client`: PHP-based ZeroMQ implementations for handling asynchronous jobs or messages between components.
- `docker/`: Docker configurations for local development environment.

## 4. Development Guidelines

### General Rules
- **Working Directory**: Always operate from the project root.
- **File Paths**: When creating or referencing files, use paths relative to the project root (e.g., `ci4/app/...` or `vue/src/...`). Do not use double paths if you have changed directory.
- **Language**: Code is written in English (variable names, comments, documentation).
- **Error Handling**: Be thorough with error handling, especially when interacting with external APIs (Kubernetes, Cloud providers).

### Backend (PHP/CI4)
- Follow PSR-12 code style.
- Use Type Hinting for all functions and methods (PHP 8 features).
- **Migrations**: Every time you change the database structure, create a new file in `ci4/app/Database/Migrations`. Use `php spark migrate` to run them.
- **Entities**: Use CodeIgniter Entities to represent rows from the database to keep models clean.
- **Routes**: Do NOT add routes to `ci4/app/Config/Routes.php`. Instead, define them in migrations using `ApiRoute` (e.g., `ApiRoute::quick(...)` or `ApiRoute::public(...)`).

### Frontend (Vue/TS)
- Use TypeScript for all new code.
- Prefer Composition API over Options API.
- Ensure types are correct and avoid `any`.

### Kubernetes Interaction
- When adding support for new Kubernetes resources, they should be implemented in `ci4/app/Libraries/Kubernetes/`.
- Use existing patterns for CRDs (see `K8sGateway.php` for reference).

## 5. Workflow for Junie
1. **Analysis**: Always start by understanding how a change affects both the database, backend, and frontend.
2. **Database**: If there are database changes, start with the migration.
3. **Logic**: Implement the backend logic and ensure correct validation.
4. **UI**: Update the frontend to match the new backend capabilities.
5. **Verification**: 
   - Run `php spark migrate` to check migrations.
   - (If possible) Run tests in `ci4/tests`.
   - Build the project if relevant to ensure there are no TypeScript compilation errors.

## 6. Useful Commands
- Migrate database: `(cd ci4 && php spark migrate)`
- Clear ORM cache: `(cd ci4 && php spark orm:clear:cache)`
- Start frontend: `(cd vue && npm run dev)`
- Sync models and api: `(cd ci4 && php spark dev:sync_models_and_api)`

## 7. Safety Protocols for Agents
- **Path Verification**: Before creating any new file or directory, run `pwd` to ensure you are at the project root (`/`). 
- **Command Isolation**: Always run commands that require directory changes in a subshell, e.g., `(cd ci4 && ...)` instead of `cd ci4 && ...`. This ensures your working directory remains consistent.
- **Root-Relative Paths**: Always use paths starting from the project root (e.g., `ci4/app/...` or `vue/src/...`) in your tool calls.
- **Double-Check**: If you just ran a backend command (in `ci4`) and are about to do frontend work, be extra vigilant about your current directory.