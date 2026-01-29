## Purpose
This document defines how to classify stakeholder communication into the correct Azure DevOps (ADO) work item type.  
All contributors and automated agents must use this guide before generating or structuring any work item.

---

# 1. Capability — When to Use
Use a **Capability** when the stakeholder describes:

- A broad functional area or domain  
- A strategic initiative  
- Work that spans multiple Features  
- A long‑term or roadmap‑level theme  

**If the request is high‑level and cannot be delivered directly → classify as Capability.**

---

# 2. Feature — When to Use
Use a **Feature** when the stakeholder describes:

- A deliverable larger than a Story  
- A functional enhancement requiring multiple Stories  
- A clear outcome that needs decomposition  
- Work that contributes to a Capability  

**If the request describes a medium‑sized deliverable → classify as Feature.**

---

# 3. Story — When to Use
Use a **Story** when the stakeholder describes:

- A specific, actionable requirement  
- A single piece of work that delivers value  
- Something that can be expressed with acceptance criteria  
- Work that can be completed by one team  

**If the request is a concrete, implementable requirement → classify as Story.**

---

# 4. Improvement Request — When to Use
Use an **Improvement Request** when the stakeholder describes:

- A proposal to improve a process, workflow, or system  
- An idea, suggestion, or optimization  
- A request that may require evaluation before implementation  
- A change that is not yet broken into Stories  

**If the request is about improving how something works → classify as Improvement Request.**

---

# 5. Service Request — When to Use
Use a **Service Request** when the stakeholder describes:

- A support need  
- A configuration, access, or maintenance request  
- Operational or administrative work  
- Non–value‑creation activities  

**If the request is for assistance or operational support → classify as Service Request.**

---

# 6. Defect — When to Use
Use a **Defect** when the stakeholder describes:

- Something broken or malfunctioning  
- Unexpected or incorrect behavior  
- A bug that needs to be fixed  
- A quality issue separate from value creation  

**If the request is about fixing something that is wrong → classify as Defect.**

---

# 7. Task — When to Use
Use a **Task** only when:

- Breaking down a Story, Defect, or Service Request  
- Tracking sub‑activities  
- Organizing implementation steps  

**Tasks are never created directly from stakeholder communication unless explicitly requested.**

---

# 8. Quick Classification Decision Tree

**Is the request describing something broken?**  
→ **Defect**

**Is it a support/operational request?**  
→ **Service Request**

**Is it a proposal to improve a process or workflow?**  
→ **Improvement Request**

**Is it a specific, actionable requirement?**  
→ **Story**

**Is it a larger deliverable requiring multiple Stories?**  
→ **Feature**

**Is it a broad functional area or strategic initiative?**  
→ **Capability**

---

# 9. Additional Rules

- If multiple classifications seem possible, choose the **lowest level** that still fits the stakeholder’s intent.  
- If the request is too vague to classify, ask for clarification.  
- Never classify a request as a Task unless explicitly instructed.  
- Always classify before applying schemas or linking rules.

---
