# DewaKoding Project Management

![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-1.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-4.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-5.jpeg)

A Laravel Filament 3 application for managing projects with ticket management and status tracking.

## Features

- Project management with ticket prefix configuration
- Role-based access control (using Filament Shield)
- Team member management with role assignments
- Customizable ticket statuses with color coding
- Ticket management with assignees and due dates
- Unique ticket identifiers
- Epic management for organizing tickets into larger initiatives
- Comment system for tickets to facilitate team discussions
- Kanban board view for visualizing ticket progress
- Assign ticket to multi users
- User contributions chart
- Timeline view
- Export ticket data to CSV
- Leaderboard for team member performance
- External dashboard view (Client Portal)

## Requirements

- PHP > 8.2+
- Laravel 12
- MySQL 8.0+ / PostgreSQL 12+
- Composer

![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-2.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-6.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-7.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-8.jpeg)
![image](https://raw.githubusercontent.com/SeptiawanAjiP/dewakoding-project-management/refs/heads/main/image-9.jpeg)


## Installation

1. Clone the repository:
   ```
   git clone https://github.com/SeptiawanAjiP/dewakoding-project-management
   cd dewakoding-project-management
   ```

2. Install dependencies:
   ```
   composer install
   npm install
   ```

3. Set up environment:
   ```
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database in `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=dewakoding_project_management
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Run migrations:
   ```
   php artisan migrate
   ```

6. Create storage link for file uploads
   ```
   php artisan storage:link
   ```

7. Create a Filament admin user:
   ```
   php artisan make:filament-user
   ```
8. Activate Role & Permission
   ```
   php artisan shield:setup
   php artisan shield:install
   php artisan shield:super-admin
   ```
9. Compile assets:
   ```
   npm run dev
   ```
10. Start the development server:
   ```
   php artisan serve
   ```

## Usage

1. Access the Filament admin panel at `http://localhost:8000/admin`
2. Log in with the Filament user credentials you created
3. Create a new project with custom ticket prefix
4. Add team members to the project
5. Create and customize ticket statuses
6. Add tickets and assign to team members

## Main Features

### Board View

The Board View offers a familiar kanban-style interface for ticket management:

- Drag-and-drop tickets between status columns
- Customize columns to match your team's process
- Quick-edit functionality for updating tickets directly from the board

### Timeline View

The Timeline feature provides a chronological perspective of your project work:

- Visualize project roadmap with start and end dates
- Track milestone completion across time periods
- Easily identify scheduling conflicts or resource bottlenecks

### Epic Management

Epics help organize related tickets into larger initiatives:

- Group tickets by feature, release, or business objective
- Track progress across multiple tickets
- Set start and end dates for planning purposes
- Visualize which tickets belong to which initiatives

### Ticket Comments

The comment system enhances team collaboration:

- Team members can discuss tickets directly in the application
- All comments are timestamped and attributed to users
- Supports rich text formatting for improved readability
- Enables better context sharing and decision documentation

## Google Login Integration

This application supports Google OAuth login. Here's how to configure it:

### 1. Getting Google OAuth Credentials

1. **Open Google Cloud Console**
   - Visit [Google Cloud Console](https://console.cloud.google.com/)
   - Sign in with your Google account

2. **Create or Select a Project**
   - Create a new project or select an existing one
   - Make sure the project is active

3. **Enable Google+ API**
   - In the sidebar, select "APIs & Services" > "Library"
   - Search for "Google+ API" and click "Enable"

4. **Create OAuth 2.0 Credentials**
   - In the sidebar, select "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth 2.0 Client IDs"
   - Select "Web application" as the application type
   - Enter application name (example: "DewaKoding Project Management")
   - In "Authorized redirect URIs", add:
     ```
     http://localhost:8000/auth/google/callback
     https://yourdomain.com/auth/google/callback
     ```
   - Click "Create"

5. **Copy Client ID and Client Secret**
   - After creation, you will get Client ID and Client Secret
   - Copy both values for application configuration

### 2. Environment Configuration

Add Google OAuth configuration in `.env` file:

```env
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
```

Replace `your_google_client_id_here` and `your_google_client_secret_here` with the values you obtained from Google Cloud Console.

### 3. How to Use

1. Open the application login page at `/admin/login`
2. Click the "Continue with Google" button
3. Sign in with your Google account
4. The application will automatically create a new account or log in to an existing account

**Note:** If the Google email is already registered in the system, the application will link the Google account with the existing account.

## Queue & Email Notifications

This application uses a queue system to send email notifications asynchronously. Here's how to configure and run it:

### 1. Email Configuration

Configure email in `.env` file:

```env
# For development (log emails to file)
MAIL_MAILER=log

# For production (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="DewaKoding Project Management"
```

### 2. Queue Configuration

Ensure queue configuration in `.env`:

```env
QUEUE_CONNECTION=database
```

### 3. Running Queue Worker

To process email notification queues, run the following commands:

#### Development Mode
```bash
php artisan queue:work
```

#### Production Mode (with automatic restart)
```bash
php artisan queue:work --daemon --tries=3 --timeout=60
```

#### Using Supervisor (Recommended for Production)

1. Install supervisor:
   ```bash
   sudo apt-get install supervisor
   ```

2. Create configuration file `/etc/supervisor/conf.d/laravel-worker.conf`:
   ```ini
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=8
   redirect_stderr=true
   stdout_logfile=/path/to/your/project/storage/logs/worker.log
   stopwaitsecs=3600
   ```

3. Restart supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-worker:*
   ```

### 4. Email Notification Types

The application sends email notifications for:
- **Project Assignment**: When a user is added to a project
- **Comment Notifications**: When there are new comments on tickets
- **Ticket Updates**: When ticket status changes

**Note:** Make sure the queue worker is always running to process email notifications. Without the queue worker, emails will not be sent.

## License

This project is licensed under the GNU General Public License v3.0 or later (GPL-3.0-or-later).

**Key Points:**
- ✅ Free to use, modify, and distribute
- ✅ Source code must remain open
- ✅ Derivative works must also be GPL-licensed
- ✅ Commercial use allowed
- ⚠️ Any modifications or derivative works must be shared under the same GPL license
- ⚠️ Must include copyright notice and license text

For the full license text, see the [LICENSE](LICENSE) file.