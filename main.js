
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
   }

};


// Initialisations
$(function(){

   // Tooltips
   $('.bptooltip').tooltip();

   $('.bpnavigate').on('click', function(){
      document.urlap.akcio.value = 'list'; // TODO
      var $this = $(this);
      document.urlap.viewday.value -= (-$this.data('offset'));
      var absolute = $this.data('absolute');
      if(typeof(absolute) != 'undefined'){ document.urlap.viewday.value = absolute; }
      document.urlap.submit();
      return false;
   });

});