# Requirements Document

## Introduction

This specification covers the Homefinder Growth Point (PGP) System feature revamp. The PGP system is a quarterly-based reward mechanism that incentivizes employees to complete training programs. Employees earn PGP points for completing training (35 PGP for free training, 15-25 PGP for paid training), and upon reaching quarterly targets, they receive monetary rewards. The system integrates with the existing Training Record menu and Approval Dashboard.

## Glossary

- **PGP_System**: The module responsible for tracking Homefinder Growth Points earned through training completion and calculating associated rewards.
- **PGP_Point**: A unit of measurement representing growth achievement through training completion.
- **Training_Type**: Classification of training as either "free" (company-provided) or "paid" (external/certification).
- **Quarterly_Period**: A three-month period (Q1: Jan-Mar, Q2: Apr-Jun, Q3: Jul-Sep, Q4: Oct-Dec) used for PGP tracking and reset.
- **PGP_Target**: The quarterly threshold of 150 PGP points that triggers the initial reward.
- **Base_Reward**: The initial monetary reward of Rp150,000 awarded upon reaching 150 PGP in a quarter.
- **Incremental_Reward**: Additional Rp35,000 awarded for each complete 35-point block earned beyond the initial 150 PGP.
- **Projected_Reward**: The calculated monetary reward based on current approved PGP points using floor logic.
- **Floor_Logic**: Mathematical calculation that only counts complete 35-point blocks for incremental rewards, discarding partial blocks.
- **TrainingRecord**: The existing model that stores employee training completion data.
- **Approval_Dashboard**: The existing Filament page for managing approval workflows.

## Requirements

### Requirement 1: Quarterly PGP Tracking

**User Story:** As an employee, I want my PGP points to reset each quarter, so that I have fresh opportunities to earn rewards throughout the year.

#### Acceptance Criteria

1. THE PGP_System SHALL track PGP points within Quarterly_Periods defined as Q1 (January 1 - March 31), Q2 (April 1 - June 30), Q3 (July 1 - September 30), and Q4 (October 1 - December 31)
2. WHEN a new Quarterly_Period begins THEN the PGP_System SHALL reset the accumulated PGP points to zero for all employees
3. WHEN calculating PGP totals THEN the PGP_System SHALL only include TrainingRecords with approval dates within the current Quarterly_Period
4. THE PGP_System SHALL preserve historical PGP data from previous quarters for reporting purposes

### Requirement 2: PGP Point Assignment

**User Story:** As an HR administrator, I want PGP points to be automatically assigned based on training type, so that the reward system is consistent and fair.

#### Acceptance Criteria

1. WHEN a TrainingRecord with Training_Type "free" is approved THEN the PGP_System SHALL assign exactly 35 PGP_Points to the employee
2. WHEN a TrainingRecord with Training_Type "paid" is approved THEN the PGP_System SHALL assign the PGP_Points value specified during approval (between 15 and 25 inclusive)
3. THE PGP_System SHALL validate that paid training PGP_Points are within the range of 15 to 25 inclusive
4. IF a paid training PGP_Points value is outside the valid range THEN the PGP_System SHALL reject the approval and display a validation error

### Requirement 3: Reward Calculation

**User Story:** As an employee, I want to see my projected reward based on my current PGP points, so that I can track my progress toward monetary incentives.

#### Acceptance Criteria

1. WHEN an employee's quarterly PGP total is less than 150 THEN the PGP_System SHALL calculate the Projected_Reward as Rp0
2. WHEN an employee's quarterly PGP total reaches exactly 150 THEN the PGP_System SHALL calculate the Projected_Reward as Rp150,000 (Base_Reward)
3. WHEN an employee's quarterly PGP total exceeds 150 THEN the PGP_System SHALL calculate the Incremental_Reward as floor((total - 150) / 35) \* Rp35,000
4. WHEN calculating total Projected_Reward THEN the PGP_System SHALL sum the Base_Reward and Incremental_Reward
5. THE PGP_System SHALL use Floor_Logic ensuring partial 35-point blocks do not contribute to the Incremental_Reward

### Requirement 4: Approval Dashboard Integration

**User Story:** As an HR administrator, I want to input PGP points when approving paid training, so that I can assign appropriate point values based on training quality.

#### Acceptance Criteria

1. WHEN displaying a paid training approval form THEN the Approval_Dashboard SHALL show a mandatory PGP_Points input field
2. THE Approval_Dashboard SHALL validate the PGP_Points input with minimum value 15 and maximum value 25
3. WHEN a free training is approved THEN the Approval_Dashboard SHALL auto-populate 35 PGP_Points without requiring manual input
4. WHEN a training approval is submitted THEN the PGP_System SHALL record the PGP_Points value and approval timestamp on the TrainingRecord

### Requirement 5: Homefinder Growth Point Menu

**User Story:** As an employee, I want to view my PGP progress and history, so that I can track my training achievements and projected rewards.

#### Acceptance Criteria

1. THE PGP_System SHALL provide a dedicated Homefinder Growth Point menu page accessible to employees
2. WHEN displaying the Homefinder Growth Point page THEN the PGP_System SHALL show a progress visualization toward the 150 PGP quarterly target
3. WHEN displaying the Homefinder Growth Point page THEN the PGP_System SHALL list all approved training records with their associated PGP_Points for the current quarter
4. WHEN displaying the Homefinder Growth Point page THEN the PGP_System SHALL show the current Projected_Reward based on approved points
5. WHEN an employee has no approved training in the current quarter THEN the PGP_System SHALL display zero progress and Rp0 projected reward

### Requirement 6: Training Record Enhancement

**User Story:** As an HR administrator, I want to manage training types and PGP points within the Training Record system, so that all training data is centralized.

#### Acceptance Criteria

1. THE TrainingRecord model SHALL include a training_type field with values "free" or "paid"
2. THE TrainingRecord model SHALL include a pgp_points field to store the assigned PGP value
3. THE TrainingRecord model SHALL include an approved_at timestamp field to track when PGP was awarded
4. WHEN creating or editing a TrainingRecord THEN the PGP_System SHALL require selection of Training_Type
5. WHEN a TrainingRecord status changes to "completed" and is approved THEN the PGP_System SHALL set the approved_at timestamp
