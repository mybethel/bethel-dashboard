App = Ember.Application.create({rootElement: '#content section.section'});

App.ApplicationAdapter = DS.RESTAdapter.extend({
    host: 'http://api.bethel.io',
    namespace: 'user/' + drupalSettings.user.uid
});

App.Location = DS.Model.extend({
    title: DS.attr('string'),
    address: DS.attr('string'),
    loc: DS.attr(),
});

App.Router.map(function() {   
    // put your routes here
});

App.IndexRoute = Ember.Route.extend({
    model: function() {
        return this.store.find('location');
    },
    events: {
        addPodcast: function() {
            App.ModalView.create({ title: "Add a Location", saveButton: "Add Location" }).append();
        }
    }
});

App.ModalView = Ember.View.extend({
    templateName: "modal",
    title: "",
    saveButton: "",
    latitude: '0',
    longitude: '0',
    classNames: ["modal", "fade"],
    didInsertElement: function() {
        this.$().modal('show');
        this.$().on('hidden.bs.modal', this._viewDidHide);
        var editForm = this;

        var locationData = new google.maps.places.Autocomplete((document.getElementById('address')), { types: ['geocode'] });
        google.maps.event.addListener(locationData, 'place_changed', function() {
            var place = locationData.getPlace();
            editForm.set('latitude', place.geometry.location.d);
            editForm.set('longitude', place.geometry.location.e);
        });
    },
    _viewDidHide: function() {
        if (!this.isDestroyed) {
            this.remove();
        }
    },
    close: function() {        
        this.$(".close").click();
    }
});
