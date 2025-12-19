# Plugin Compatibility Assessment

## Plugin: ffhs/filament-package_ffhs_approvals v1.0.1

## Overview

This document assesses the compatibility between the `ffhs/filament-package_ffhs_approvals` plugin and the existing HRIS approval workflow system.

## Plugin Features

### Core Features

-   ✅ Native PHP Enums for status handling
-   ✅ Multiple approval flows per model
-   ✅ Role-, user-, and permission-based approval logic
-   ✅ Seamless integration with Filament Actions and Forms
-   ✅ Customizable icons, labels, tooltips, colors per status
-   ✅ Control button visibility and approval flow states
-   ✅ Built-in confirmation prompts and notifications

### Plugin Architecture

-   **Approvable Contract**: Interface for models requiring approval
-   **HasApprovals Trait**: Provides approval relationship and status methods
-   **SimpleApprovalFlow**: Defines approval workflow with multiple approval groups
-   **SimpleApprovalBy**: Defines who can approve (role, permission, user, or any)
-   **ApprovalActions**: Filament Infolist component for approval UI

### Plugin Database Schema

The plugin creates an `approvals` table with:

-   `key`: Approval flow identifier
-   `approvable_type/id`: Polymorphic relation to approvable model
-   `status`: Approval status (enum-based)
-   `approval_by`: Name of the approval group
-   `approver_type/id`: Polymorphic relation to approver (User)
-   Soft deletes support

## Existing System Architecture

### Current Models

1. **ApprovalWorkflow**: Defines workflow templates per module (leave, reimbursement, payroll_adjustment)
2. **ApprovalStep**: Tracks individual approval steps with status, approver, notes
3. **LeaveRequest**: Has `approvalSteps()` morphMany relationship
4. **Reimbursement**: Has `approvalSteps()` morphMany relationship

### Current Service

-   **ApprovalWorkflowService**: Handles workflow initialization, approve/reject actions, notifications

## Compatibility Analysis

### ✅ Compatible Features

| Requirement               | Plugin Support                      | Notes                      |
| ------------------------- | ----------------------------------- | -------------------------- |
| Role-based approvers      | ✅ `SimpleApprovalBy::role()`       | Direct support             |
| User-based approvers      | ✅ `SimpleApprovalBy::permission()` | Via permissions            |
| Multiple approval steps   | ✅ Multiple `SimpleApprovalBy`      | Sequential via `atLeast()` |
| Approval status tracking  | ✅ Enum-based statuses              | Custom enum required       |
| Filament UI integration   | ✅ `ApprovalActions` component      | Native Filament support    |
| Polymorphic relationships | ✅ `morphMany` approvals            | Same pattern as existing   |

### ⚠️ Gaps Requiring Customization

| Requirement                           | Gap                                  | Solution                                             |
| ------------------------------------- | ------------------------------------ | ---------------------------------------------------- |
| Existing ApprovalStep records         | Plugin uses different table          | Create migration adapter or parallel system          |
| Workflow templates (ApprovalWorkflow) | Plugin defines flows in model        | Hybrid approach: keep templates, map to plugin flows |
| Sequential step advancement           | Plugin uses parallel approval groups | Use `atLeast(1)` per group for sequential            |
| Notification system                   | Plugin has basic notifications       | Enhance with existing NotificationService            |
| Approval history with notes           | Plugin stores status only            | Extend Approval model or use existing ApprovalStep   |
| Bulk approval                         | Not built-in                         | Implement in enhanced service                        |
| Approval dashboard                    | Not included                         | Create custom Filament page                          |

### 🔄 Integration Strategy

#### Hybrid Approach (Recommended)

1. **Keep existing ApprovalWorkflow/ApprovalStep** for backward compatibility and detailed tracking
2. **Add plugin's Approvable trait** to models for UI components
3. **Create adapter service** that bridges plugin flows with existing workflow service
4. **Use plugin's ApprovalActions** for Filament UI
5. **Enhance ApprovalWorkflowService** with new methods for dashboard/bulk operations

#### Model Changes Required

```php
// LeaveRequest - Add plugin trait alongside existing relationships
class LeaveRequest extends Model implements Approvable
{
    use HasApprovals; // Plugin trait

    // Keep existing approvalSteps() relationship

    public function getApprovalFlows(): array
    {
        // Map from ApprovalWorkflow configuration
    }
}
```

#### Status Enum Required

```php
enum LeaveApprovalStatus: string implements HasApprovalStatuses
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function getApprovedStatuses(): array { return [self::APPROVED]; }
    public static function getDeniedStatuses(): array { return [self::REJECTED]; }
    public static function getPendingStatuses(): array { return [self::PENDING]; }
}
```

## Customizations Needed

### 1. Create Approval Status Enum

-   Create `App\Enums\ApprovalStatus` implementing `HasApprovalStatuses`
-   Map to existing status constants

### 2. Enhance Approvable Models

-   Add `Approvable` interface and `HasApprovals` trait to LeaveRequest, Reimbursement
-   Implement `getApprovalFlows()` method that reads from ApprovalWorkflow

### 3. Create Approval Dashboard

-   New Filament page for centralized approval management
-   Use plugin's `ApprovalActions` component

### 4. Enhance ApprovalWorkflowService

-   Add `getPendingApprovalsForUser()` method
-   Add `getPendingApprovalsByModule()` method
-   Add `bulkApprove()` method
-   Add `getApprovalStatistics()` method

### 5. Notification Integration

-   Hook plugin approval events to existing NotificationService
-   Maintain existing notification format

## Conclusion

The plugin provides excellent UI components and a solid foundation for approval workflows. The recommended approach is a **hybrid integration** that:

1. Preserves existing data structures and business logic
2. Leverages plugin UI components for better user experience
3. Extends the service layer for dashboard and bulk operations
4. Creates adapter methods to bridge the two systems

This approach minimizes migration risk while gaining the benefits of the plugin's Filament integration.
