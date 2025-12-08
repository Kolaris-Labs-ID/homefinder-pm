# Implementation Plan

- [x]    1. Add status filter property and query modification

           - [x] 1.1 Add public `$statusFilter` property to WeeklyTodoOverviewWidget
                - Add property with default value 'all'
                - Ensure property is reactive for Livewire updates
                - _Requirements: 1.1, 2.1_
           - [x] 1.2 Modify table query to apply status filter
                - Add conditional where clause when statusFilter is not 'all'
                - Ensure filter works with existing query constraints (user/division filtering)
                - _Requirements: 1.3, 1.4_
           - [x] 1.3 Write property test for status filter query
                - **Property 1: Status filter returns only matching todos**
                - **Validates: Requirements 1.3**

- [ ]    2. Implement dropdown in Status column header

     - [ ] 2.1 Replace static Status label with dropdown select
          - Create inline select component in column header
          - Wire to statusFilter property with wire:model.live
          - Include options: All, Pending, In Progress, Done
          - _Requirements: 1.1, 1.2, 1.5, 3.1, 3.2_
     - [ ] 2.2 Write property test for all filter behavior
          - **Property 2: All filter returns todos of every status**
          - **Validates: Requirements 1.4**

- [ ]    3. Remove redundant status filter from filters panel

     - [ ] 3.1 Remove SelectFilter for status from table filters
          - The dropdown in column header replaces this functionality
          - Keep other filters (user, division, week date, this week)
          - _Requirements: 1.1_

- [ ]    4. Checkpoint - Ensure all tests pass
     - Ensure all tests pass, ask the user if questions arise.
