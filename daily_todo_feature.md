You are working on a Laravel + Filament project for project management.  
Now you need to add a **Daily To-Do List** feature.

🧩 Tech Stack:
- Laravel
- FilamentPHP (for admin/dashboard)
- Follows existing design system (use the same components, typography, and color scheme)

🎯 Feature Goal:
Allow each user (employee) to:
- Create, view, and update their **daily to-do list**
- Each to-do list is tied to a **specific date** (usually today)
- Each item has: title, description (optional), status (Pending / In Progress / Done)

👥 Roles:
- **User (Employee)**: can only view & edit their own to-do list.
- **Head Division**: can view to-do lists of all users in their division.
- **Superadmin / HR**: can view all to-do lists across divisions.

📊 Dashboard integration:
- Add a new section “Daily To-Do” in the Superadmin/HR dashboard and Head Division dashboard.
- Show a table (or cards) grouped by division and user, showing their to-do items for today.
- Include filters by date, division, and user.

🧱 Database suggestion:
Table: `daily_todos`
Fields:
- id
- user_id (foreign key)
- date (date)
- title (string)
- description (text, nullable)
- status (enum: pending, in_progress, done)
- created_at / updated_at

⚙️ Required:
1. Filament Resource for DailyTodo (CRUD)  
2. Policies to restrict access (user sees only their own, head division sees their division)  
3. Dashboard widgets for:
   - Superadmin/HR overview (all users)
   - Head division overview (their team only)
   - Optional: User widget showing today’s list & quick add form
4. Follow the existing Filament design system and consistent UI components.

Now generate all necessary Laravel + Filament code to implement this feature, including:
- Migration
- Model
- Policy
- Filament Resource (forms, tables)
- Dashboard widgets
- Routes & relationships
