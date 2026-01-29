# ADO_SCHEMAS.md

## Purpose
This document defines the required fields and JSON schemas for each Azure DevOps (ADO) work item type used in the EO project.  
All contributors and automated agents must use these schemas when generating or validating work items.

---

## Capability — JSON Schema
```json
{
  "type": "Capability",
  "title": "",
  "description": "",
  "definition_of_done": "",
  "due_date": ""
}
```
## Feature — JSON Schema
```json
{
  "type": "Feature",
  "title": "",
  "description": "",
  "acceptance_criteria": "",
  "start_date": "",
  "target_date": "",
  "effort": ""
}
```
## Improvement Request — JSON Schema
```json
{
  "type": "Improvement Request",
  "title": "",
  "description": "",
  "benefit_hypothesis": "",
  "expected_outcome": "",
  "effort": ""
}
```
## Story — JSON Schema
```json
{
  "type": "Story",
  "title": "",
  "description": "",
  "acceptance_criteria": "",
  "effort": ""
}
```
## Service Request — JSON Schema
```json
{
  "type": "Service Request",
  "title": "",
  "description": "",
  "acceptance_criteria": "",
  "effort": ""
}
```
### Notes
- All fields must be populated.
- If stakeholder input does not provide enough detail, reasonable defaults may be used (e.g., "effort": "TBD").
- Acceptance criteria must always be written as clear, testable statements.
- Only the fields listed above are allowed; no additional fields should be added.

