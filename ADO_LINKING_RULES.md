## Purpose
This document defines the rules for linking Azure DevOps (ADO) work items within the EO project.  
Only two link types are allowed:

- **children**
- **related**

All contributors and automated agents must follow these rules to maintain consistent and correct work item relationships.

---

# 1. Story Linking Rules
- A **Story** must be linked in exactly one of the following ways:
  - **children → Feature**, or  
  - **related → Objective**, or  
  - **related → Improvement Request**
- A Story must **never be orphaned**.
- If its parent Feature is already related to an Objective or Improvement Request,  
  **the Story must NOT also be related** to that same item.

---

# 2. Feature Linking Rules
- A **Feature** must be linked in exactly one of the following ways:
  - **children → Capability**, or  
  - **related → Objective**, or  
  - **related → Improvement Request**
- If its parent Capability is already related to an Objective or Improvement Request,  
  **the Feature must NOT also be related** to that same item.
- Stories must be linked to Features using **children**.

---

# 3. Capability Linking Rules
- A **Capability** must be linked using **related** to:
  - an **Objective**, or  
  - an **Improvement Request**
- Capabilities are the highest‑level containers.  
  Only the Capability should be related to Objectives or Improvement Requests, not its children.

---

# 4. Improvement Request Linking Rules
- An **Improvement Request** may be related to:
  - Risks  
  - Capabilities  
  - Features  
  - Stories  
  - Service Requests
- Only the **highest‑level item** in a hierarchy should be related.
  - If a Capability is related → its Features and Stories must NOT be related.
  - If a Feature is related → its Stories must NOT be related.

---

# 5. Objective Linking Rules
- An **Objective** may be related to:
  - Risks  
  - Capabilities  
  - Features  
  - Stories
- Only the **highest‑level item** should be related.
  - If a Capability is related → its Features and Stories must NOT be related.
  - If a Feature is related → its Stories must NOT be related.

---

# 6. Risk Linking Rules
- A **Risk** may be related to:
  - Capabilities  
  - Features  
  - Stories
- Only the **highest‑level affected item** should be related.
  - If a Capability is related → its Features and Stories must NOT be related.
  - If a Feature is related → its Stories must NOT be related.

---

# 7. Defect Linking Rules
- A **Defect** does **not** require any link.
- Optional valid links:
  - **children → Feature**
  - **related → Risk**
- Defects must **never** be related to Objectives or Improvement Requests.

---

# 8. Service Request Linking Rules
- A **Service Request** does **not** require any link.
- Optional valid links:
  - **related → Risk**
  - **related → Improvement Request**
- Service Requests must **never** be related to Objectives.

---

# 9. Task Linking Rules
- A **Task** must have exactly one parent:
  - **children → Story**, or  
  - **children → Defect**, or  
  - **children → Service Request**
- Tasks must **never** be related to Objectives, Risks, Capabilities, Features, or Improvement Requests.

---

# 10. Highest‑Level Linking Rule (Critical)
When linking to **Objectives**, **Improvement Requests**, or **Risks**,  
**only the highest‑level work item in the hierarchy should be linked**.

Hierarchy (highest → lowest):
1. Capability  
2. Feature  
3. Story  
4. Task  

Examples:
- If a Capability is linked → do NOT link its Features or Stories.  
- If a Feature is linked → do NOT link its Stories.  
- If a Story is linked → do NOT link its Tasks.

---

# 11. Orphan Rule (Critical)
A **Story must never be orphaned**.

A Story is orphaned if it is:
- not a child of a Feature,  
- AND not related to an Objective,  
- AND not related to an Improvement Request.

Such Stories are invalid and must be corrected.

---
