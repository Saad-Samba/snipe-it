## Purpose
This document defines each Azure DevOps (ADO) work item type used in the EO project.  
It explains the intent, scope, and appropriate usage of each type so that contributors and automated agents can create consistent, well‑structured work items.

---

## Capability
**Purpose:**  
A high‑level container representing a broad functional area or strategic initiative.  
Used to group multiple Features under a common theme.

**When to Use:**  
- When the work spans multiple Features  
- When organizing a large domain or long‑term initiative  
- When providing structure for roadmap‑level planning

---

## Feature
**Purpose:**  
A mid‑level deliverable that groups Stories.  
Represents a functional enhancement or outcome that requires multiple Stories to complete.

**When to Use:**  
- When a stakeholder describes a deliverable larger than a Story  
- When multiple Stories contribute to a single outcome  
- When organizing work under a Capability

---

## Story
**Purpose:**  
The smallest unit of value delivery.  
Represents a specific, actionable requirement that can be completed by a team.

**When to Use:**  
- When the stakeholder describes a single piece of work  
- When acceptance criteria can be clearly defined  
- When the work directly delivers value

---

## Improvement Request
**Purpose:**  
Captures proposals for process or system improvements.  
May or may not require implementation work; if it does, Stories are created and linked.

**When to Use:**  
- When a stakeholder suggests an improvement or optimization  
- When evaluating potential benefits or outcomes  
- When the request is not yet broken into actionable Stories

---

## Service Request
**Purpose:**  
Represents operational or support‑type work.  
Often created through the Request Launcher to ensure correct routing.

**When to Use:**  
- When the stakeholder needs assistance, configuration, access, or maintenance  
- When the request is not value‑creation work  
- When the work is service‑oriented rather than feature‑oriented

---

## Defect
**Purpose:**  
Tracks issues, bugs, or unexpected behavior separate from value‑creation work.

**When to Use:**  
- When something is broken or not functioning as expected  
- When the issue needs to be fixed independently of Stories or Features  
- When tracking quality‑related work

---

## Task
**Purpose:**  
A low‑level activity used to break down Stories, Defects, or Service Requests.

**When to Use:**  
- When organizing work inside a Story, Defect, or Service Request  
- When breaking implementation into smaller steps  
- When tracking sub‑activities that do not represent standalone value

---

This document defines the intent of each work item type.  
For required fields and JSON schemas, refer to **ADO_SCHEMAS.md**.  
For linking rules, refer to **ADO_LINKING_RULES.md**.
