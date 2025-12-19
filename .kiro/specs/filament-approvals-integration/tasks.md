# Implementation Plan

-   [x] 1. Install and configure the filament-approvals plugin

    -   [x] 1.1 Install the plugin via Composer
        -   Run `composer require ffhs-it/filament-approvals`
        -   Publish plugin configuration and assets
        -   _Requirements: 4.1, 4.2_
    -   [x] 1.2 Review plugin documentation and assess compatibility
        -   Document any gaps between plugin features and requirements
        -   Identify customizations needed
        -   _Requirements: 2.1, 2.2_

-   [x] 2. Enhance approvable models with plugin trait

    -   [x] 2.1 Add Approvable trait to LeaveRequest model
        -   Implement plugin's Approvable interface/trait
        -   Ensure existing relationships are preserved
        -   _Requirements: 4.1, 2.2_
    -   [x] 2.2 Write property test for approver type resolution
        -   **Property 3: Approver type resolution**
        -   **Validates: Requirements 2.3**
    -   [x] 2.3 Add Approvable trait to Reimbursement model
        -   Implement plugin's Approvable interface/trait
        -   Ensure existing relationships are preserved
        -   _Requirements: 4.1, 2.2_
    -   [x] 2.4 Write property test for sequential step execution
        -   **Property 4: Sequential step execution**
        -   **Validates: Requirements 2.4**

-   [x] 3. Enhance ApprovalWorkflowService for plugin integration

    -   [x] 3.1 Add getPendingApprovalsForUser method
        -   Query all pending approval steps for a given user
        -   Support both direct user assignment and role-based assignment
        -   _Requirements: 5.1_
    -   [x] 3.2 Add getPendingApprovalsByModule method
        -   Filter pending approvals by module type
        -   _Requirements: 5.2_
    -   [x] 3.3 Write property test for module filter correctness
        -   **Property 9: Module filter correctness**
        -   **Validates: Requirements 5.2**
    -   [x] 3.4 Add bulkApprove method
        -   Process multiple approval steps in a single transaction
        -   Return success/failure status for each item
        -   _Requirements: 5.4_
    -   [x] 3.5 Write property test for bulk approval atomicity
        -   **Property 10: Bulk approval atomicity**
        -   **Validates: Requirements 5.4**
    -   [x] 3.6 Add getApprovalStatistics method
        -   Return counts of pending, approved, rejected items
        -   _Requirements: 3.2_

-   [x] 4. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 5. Create Approval Dashboard page

    -   [x] 5.1 Create ApprovalDashboard Filament page
        -   Add navigation to HRIS group
        -   Display pending approvals in a table
        -   _Requirements: 5.1, 5.3_
    -   [x] 5.2 Write property test for dashboard pending items completeness
        -   **Property 8: Dashboard pending items completeness**
        -   **Validates: Requirements 5.1**
    -   [x] 5.3 Add module filter to dashboard
        -   Allow filtering by leave, reimbursement, payroll_adjustment
        -   _Requirements: 5.2_
    -   [x] 5.4 Add bulk approval actions to dashboard
        -   Implement bulk approve and bulk reject actions
        -   _Requirements: 5.4_
    -   [x] 5.5 Write property test for pending count accuracy
        -   **Property 6: Pending count accuracy**
        -   **Validates: Requirements 3.2**

-   [x] 6. Create approval action components

    -   [x] 6.1 Create ApproveAction Filament action
        -   Modal with optional notes field
        -   Call ApprovalWorkflowService::approve
        -   _Requirements: 1.1, 1.3_
    -   [x] 6.2 Create RejectAction Filament action
        -   Modal with required reason field
        -   Call ApprovalWorkflowService::reject
        -   _Requirements: 1.1, 1.3_
    -   [x] 6.3 Add approval actions to LeaveRequestResource
        -   Add approve/reject actions to table and view page
        -   Show only when user is authorized approver
        -   _Requirements: 1.1_
    -   [x] 6.4 Write property test for approval action buttons presence
        -   **Property 1: Approval action buttons presence**
        -   **Validates: Requirements 1.1**
    -   [x] 6.5 Add approval actions to ReimbursementResource
        -   Add approve/reject actions to table and view page
        -   Show only when user is authorized approver
        -   _Requirements: 1.1_

-   [x] 7. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 8. Implement approval history display

    -   [x] 8.1 Create ApprovalHistory Livewire component
        -   Display timeline of approval steps
        -   Show approver name, timestamp, status, notes
        -   _Requirements: 1.2, 1.4_
    -   [x] 8.2 Write property test for approval history completeness
        -   **Property 2: Approval history completeness**
        -   **Validates: Requirements 1.4**
    -   [x] 8.3 Add approval history to approvable resource view pages
        -   Integrate component into LeaveRequestResource view
        -   Integrate component into ReimbursementResource view
        -   _Requirements: 1.2_

-   [x] 9. Enhance notification system

    -   [x] 9.1 Update approver notification on step advancement
        -   Ensure notification is created when step becomes pending
        -   Include link to the approvable item
        -   _Requirements: 3.1_
    -   [x] 9.2 Write property test for approver notification on step advancement
        -   **Property 5: Approver notification on step advancement**
        -   **Validates: Requirements 3.1**
    -   [x] 9.3 Add requestor notification on status change
        -   Notify requestor when item is approved or rejected
        -   Include final status and any notes/reason
        -   _Requirements: 3.3_
    -   [x] 9.4 Write property test for requestor notification on status change
        -   **Property 7: Requestor notification on status change**
        -   **Validates: Requirements 3.3**
    -   [x] 9.5 Add navigation badge for pending approvals
        -   Show count of pending items in navigation
        -   Update dynamically
        -   _Requirements: 3.2_

-   [x] 10. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 11. Implement workflow modification isolation

    -   [x] 11.1 Add workflow version tracking
        -   Store workflow snapshot when approval is initialized
        -   Ensure in-progress approvals use original configuration
        -   _Requirements: 6.3_
    -   [x] 11.2 Write property test for workflow modification isolation
        -   **Property 11: Workflow modification isolation**
        -   **Validates: Requirements 6.3**
    -   [x] 11.3 Ensure active workflow selection
        -   Verify only active workflows are used for new requests
        -   _Requirements: 6.4_
    -   [x] 11.4 Write property test for active workflow selection
        -   **Property 12: Active workflow selection**
        -   **Validates: Requirements 6.4**

-   [x] 12. Final Checkpoint - Ensure all tests pass
    -   Ensure all tests pass, ask the user if questions arise.
