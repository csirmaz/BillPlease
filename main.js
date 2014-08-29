
BP = {

   // Check if a string is a float
   checkfl: function(v, msg){
      if(v.match(/^\-?[\d\.]+$/)){ // returns null?
         if(v.match(/\./g).length<=1){
            return parseFloat(v);
         }
      }
      alert(msg + ' is not a number');
      return false;
   }

   // Check if a string is an integer
   checkint: function(v, msg){
      if(v.match(/^\d+$/)){ return parseInt(v); }
      alert(msg + ' is not an integer number');
      return false;
   }

};


// Initialisations
$(function(){

   // Tooltips
   $('.bptooltip').tooltip();

});