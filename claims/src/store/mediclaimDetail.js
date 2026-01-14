import { defineStore } from "pinia";
export const useDetailStore = defineStore ('mediAdmissibleAmt',{
    state:()=>({
        ipd_amount:0,
        opd_amount:0,
        test_investigation_amount:0,
        TotalAdmissibleAmt:0,
        TotalAmt:0
    }) ,
    actions:{
        setTotalAdmissibleAmt(admisibleAmt){
             this.TotalAdmissibleAmt = admisibleAmt
         },
          setTotalAmt(amt){
             this.TotalAmt = amt
         },
        setIpdAmt(newIpdAmt){
            this.ipd_amount = newIpdAmt
        },
        setOptAmt(newOpdAmt){
            this.opd_amount = newOpdAmt
        },
        setTestInvstAmt(newTestInvstAmt){
            this.test_investigation_amount = newTestInvstAmt
        },
    }
});