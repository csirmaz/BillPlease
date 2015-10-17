
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
   },

   // Check if a string is an integer
   checkint: function(v, msg){
      if(v.match(/^\d+$/)){ return parseInt(v); }
      alert(msg + ' is not an integer number');
      return false;
   },

   checkintlist: function(v, msg){
      if(v.match(/^[\d,]+$/)){ return true; }
      alert(msg + ' is not a list of integers');
      return false;
   }

};


// Initialisations
$(function(){

   $('.bpnavigate').on('click', function(){
      document.urlap.akcio.value = 'list'; // TODO
      var $this = $(this);
      document.urlap.viewday.value -= (-$this.data('offset'));
      var absolute = $this.data('absolute');
      if(typeof(absolute) != 'undefined'){ document.urlap.viewday.value = absolute; }
      document.urlap.submit();
      return false;
   });

   $('.bpheader .bpname').on('click', function(){
      document.urlap.akcio.value = 'newon'; // TODO
      var $this = $(this);
      document.urlap.iuday.value = $this.closest('.bpheader').data('uday');
      document.urlap.submit();
      return false;
   });

   $('.calculator').on('click', function(){
      var s = 0;
      var x;
      while(true){
         x = prompt("Sum so far: "+(s/100)+". Enter a number to add, 'c' to copy the sum, or 'a' to abort");
         if(x=='c'){
            $('.calculator_value').val(Math.floor(s)/100);
            break;
         }
         if(x=='a'){ break; }
         s += Math.floor(x*100+.5);
      }
   });

});