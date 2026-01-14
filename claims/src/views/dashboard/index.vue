 
<template>
  <main class="app-wrapper">
    <!-- start header -->
     Loading...
    </main>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { onMounted } from 'vue';
import LINKS from "@/constant/links";
import {hasAnyPermission, hasPermission, PERMISSIONS } from "@/utils/rbac";
const router = useRouter();
 onMounted(() => { 
   if (hasAnyPermission([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE]) ) {  
  router.push({ 
      name: 'ITClaimList'
    });
 } else if (hasPermission(PERMISSIONS.CGHS.MANAGE_CARD) && !hasPermission(PERMISSIONS.CGHSAPPROVE.APPROVE) ) {  
  router.push(LINKS.CGHSCard);
  //console.log('review only','----',PERMISSIONS.CGHS.MANAGE_CARD);   
 }else if (hasPermission(PERMISSIONS.CGHSAPPROVE.APPROVE) && !hasPermission(PERMISSIONS.CGHS.MANAGE_CARD) ) { 
  router.push(LINKS.CGHSCardApprove); 
  //console.log('approver only','----',PERMISSIONS.CGHS.MANAGE_CARD);   
 }else if (hasPermission([PERMISSIONS.CGHSAPPROVE.MANAGE_CARD,PERMISSIONS.CGHSAPPROVE.APPROVE])){
    //console.log('both','----',PERMISSIONS.CGHSAPPROVE.MANAGE_CARD,'====',PERMISSIONS.CGHSAPPROVE.APPROVE);
    return  router.push(LINKS.CGHSCardApprove);
 }else if(hasAnyPermission([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE]) ) {  
  return  router.push(LINKS.CLAIM.IT);
 }
 });
 //console.log('ddd==',PERMISSIONS.CGHS.MANAGE_CARD,'----',PERMISSIONS.CGHSAPPROVE.APPROVE); 
</script> 