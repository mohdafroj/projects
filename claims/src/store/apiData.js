import { defineStore } from 'pinia';

export const useApiStore = defineStore('apiData', {
    state: () => ({
        user: {},
        rbac: {},
        it_equipment_final_data: {},
        it_claim_templates: [],
        it_equipment: { list: {}, detail: { touched_items: [], documents: [] }, action: {} },
        tada_claim: { list: {}, detail: { touched_items: [], documents: [] }, action: {} },
    }),
    actions: {
        setUser(data) {
            this.user = data;
        },
        setRbac(data) {
            this.rbac = data;
        },
        setEncrypted(data) {
            this.encryptData = data
        },
        setItEquipment(data) {
            this.it_equipment = data;
        },
        setItClaimFinalData(data) {
            this.it_equipment_final_data = data;
        },
        setITClaimTemplates(data) {
            this.it_claim_templates = data;
        },
        setItEquipmentAct(data) {
            this.it_equipment.action = data;
        },
          setTadaClaim(data) {
            this.tada_claim = data;
        },
    },
});