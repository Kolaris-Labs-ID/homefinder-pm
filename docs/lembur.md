Since `expo-camera` is notoriously finicky on web browsers (and often overkill for simple evidence), we’ll pivot to **`expo-image-picker`**. It’s much more reliable across iOS, Android, and Web because it leverages the system's native UI to capture or pick photos.

Here is the full prompt for your AI Agent, formatted for clear execution.

---

# **AI Agent Prompt: Overtime (Lembur) ESS Module**

**Objective:** Build a "Time-Tracker" style Overtime feature for an HRIS. This is an additive **Employee Self Service (ESS)** menu. Employees "Start" a timer for a task and "Stop" it with optional photo evidence.

---

## **1. Backend: Database & Business Logic (Laravel)**

### **Migration: `overtime_logs`**
Create a migration with the following fields:
* `user_id` (foreignId to users)
* `manager_id` (foreignId to users - the assigner)
* `client_id` (nullable foreignId to clients)
* `task_name` (string), `description` (text, nullable)
* `start_time` (timestamp), `end_time` (timestamp, nullable)
* `duration_minutes` (integer, default 0)
* `evidence_path` (string, nullable)
* `status` (enum: `running`, `pending`, `approved`, `rejected`)
* `rejection_note` (text, nullable)

### **Model Logic (`OvertimeLog.php`)**
Implement a method to calculate the "Lembur" pay based on Indonesian Labor Law:
* First hour: $1.5 \times \text{hourly\_rate}$
* Subsequent hours: $2.0 \times \text{hourly\_rate}$
* *Note: Ensure this logic is available for the Filament dashboard to show estimated costs.*

---

## **2. Backend: API Endpoints**

Implement a controller to handle the mobile app requests:
* **`POST /api/overtime/start`**: 
    * Validates that the user doesn't have an active `running` session.
    * Saves `manager_id`, `task_name`, and `client_id`.
    * Returns the `start_time` and `id`.
* **`POST /api/overtime/stop`**: 
    * Updates `end_time` and calculates `duration_minutes`.
    * **Validation:** If `client_id` is present, `evidence_photo` is **required** via `multipart/form-data`.
    * Moves status to `pending`.
* **`GET /api/overtime/active`**: Checks if the user has a `running` session to sync the app UI on refresh.

---

## **3. Frontend: Mobile & Web App (Expo)**

**Requirement:** Use `expo-image-picker` for cross-platform compatibility (Web/Mobile).

### **UI States:**
1.  **Selection Screen:** 
    * Form to pick a **Manager** (Assigner) and a **Client** (Optional).
    * Text field for **Task Name**.
2.  **Active Tracker Screen:** 
    * Displays a live timer (HH:mm:ss). 
    * *Logic:* Calculate the difference between `new Date()` and the `start_time` fetched from the server.
3.  **Completion Logic:**
    * When "Stop" is pressed: If a client was selected, trigger `ImagePicker.launchCameraAsync()` (or `launchImageLibraryAsync` for web fallback).
    * Upload the photo and session data to the backend.

---

## **4. Admin Dashboard (Filament PHP)**

### **Resource: `OvertimeResource`**
* **Table View:**
    * `TextColumn` for Employee Name and Task.
    * `ImageColumn` for `evidence_path` (square/circular preview).
    * `BadgeColumn` for Status (Running = Gray, Pending = Amber, Approved = Success).
* **Actions:**
    * **Approve/Reject:** Modal actions to update status. 
    * **Bulk Approval:** Allow HR to approve multiple entries at once.
* **Widgets:** Create a "Live Overtime" header widget showing users currently in the `running` state.

---

## **5. Critical Safety Logic**

* **Auto-Close Command:** Create a `LemburAutoClose` command. If a session is `running` for > 6 hours, automatically set the `end_time` to 4 hours after the start and mark it as "Flagged" for HR review. This prevents "forgotten timers" from ruining payroll.
* **Image Compression:** On the Laravel side, use `Spatie Image` to resize uploads to a maximum width of 1200px to save storage space.

---

### **A quick "Honest" Note on Expo Web:**
Since `expo-camera` is a headache on web, using `expo-image-picker` with `launchCameraAsync` will work on mobile, while on Web browsers, it automatically falls back to the native file picker (`<input type="file">`), which is exactly what you want for a stable cross-platform ESS.
