import { defineStore } from "pinia";
export const storeCardDetails = defineStore('storeCghsCardDetails',{
state:()=>({
    memberinfo_form1:{
        nameEnglish:'',
        nameHindi:'',
        card_type:'',
        ic_number:'',
        dob:'',
        gender:'',
        bloodGroup:'',
        pan:'',
        aadhaar:'',
        wellnessCentre:''        
    },
    srchName:{
        username:'',
        core_user_id:''
    },
    apiDataStored:'',
    officeAddress_1:'',
    homeAddress_1:'',
    familyDetails:'',
    coreUserId:''
}),
actions:{
    setCoreUserId(id){
        this.coreUserId = id;    
     },
    setMemObj(name_en,name_hi,cardType,icNumber,dob,gender,bloodGroup,pan,aadhaar,wellnessCentre,marriageDate){    
        this.memberinfo_form1.nameEnglish = name_en; 
        this.memberinfo_form1.nameHindi = name_hi; 
        this.memberinfo_form1.card_type = cardType; 
        this.memberinfo_form1.ic_number = icNumber; 
        this.memberinfo_form1.dob = dob; 
        this.memberinfo_form1.gender = gender; 
        this.memberinfo_form1.bloodGroup = bloodGroup; 
        this.memberinfo_form1.pan = pan; 
        this.memberinfo_form1.aadhaar = aadhaar; 
        this.memberinfo_form1.wellnessCentre = wellnessCentre;
        this.memberinfo_form1.marriageDate = marriageDate;
        
    },
     setSrchName(username,core_user_id){
        this.srchName.username = username;
        this.srchName.core_user_id = core_user_id;
     },
     setApiDataStored(userDetail){
        this.apiDataStored = userDetail;    
     },
     setOfficeAddress(OffAddress){
        //console.log('hereeeee==')
        this.officeAddress_1 = OffAddress;    
     },
     setHomeAddress(hAddress){
        this.homeAddress_1 = hAddress;    
     },
     setFamilyDetails(memberFamilyDetails){
        this.familytDetails = memberFamilyDetails;    
     }
}
});