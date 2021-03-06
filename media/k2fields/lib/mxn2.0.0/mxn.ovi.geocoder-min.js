/*
MAPSTRACTION   v2.0.0   http://www.mapstraction.com

The BSD 3-Clause License (http://www.opensource.org/licenses/BSD-3-Clause)

Copyright (c) 2012 Tom Carden, Steve Coast, Mikel Maron, Andrew Turner, Henri Bergius, Rob Moran, Derek Fowler, Gary Gale
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of the Mapstraction nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
mxn.register("ovi",{Geocoder:{init:function(){var b=this;var a;if(ovi.mapsapi){a=new ovi.mapsapi.search.Manager();a.addObserver("state",function(d,c,e){if(e=="finished"||e=="failed"){b.geocode_callback(d.locations,e)}});this.geocoders[this.api]=a}else{alert(api+" map script not imported")}},geocode:function(b){var a=this.geocoders[this.api];a.geocode(b)},geocode_callback:function(c,d){var b=this.geocoders[this.api];if(d=="failed"){var j=b.getErrorCause();var k="";if(j.type){k=j.type;if(j.subtype){k+=", "+j.subtype}if(j.message){k+=", "+j.message}}else{k="Geocoding failure"}this.error_callback(k)}else{if(d=="finished"){var g={};var e=[];var a=[];var f=[];g.street="";g.locality="";g.postcode="";g.region="";g.country="";if(c.length>0){var i=c[0].address;var h=c[0].displayPosition;if(i.street){e.push(i.street)}if(i.houseNumber){e.unshift(i.houseNumber)}if(i.city){a.push(i.city)}if(i.district){a.unshift(i.district)}if(i.postalCode){g.postcode=i.postalCode}if(i.state){f.unshift(i.state)}if(i.county){f.push(i.county)}if(i.country){g.country=i.country}if(g.street===""&&e.length>0){g.street=e.join(" ")}if(g.locality===""&&a.length>0){g.locality=a.join(", ")}if(g.region===""&&f.length>0){g.region=f.join(", ")}g.point=new mxn.LatLonPoint(h.latitude,h.longitude);this.callback(g)}}}}}});