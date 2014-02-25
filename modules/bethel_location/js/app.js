App = Ember.Application.create({rootElement: '#content section.section'});

App.ApplicationAdapter = DS.RESTAdapter.extend({
    host: 'http://api.bethel.io',
    namespace: 'user/' + drupalSettings.user.uid
});

App.ApplicationSerializer = DS.RESTSerializer.extend({
    primaryKey: function(type) {
        return '_id';
    },
    serializeId: function(id) {
        return id.toString();
    }
});

App.Location = DS.Model.extend({
    title: DS.attr('string'),
    address: DS.attr('string'),
    loc: DS.attr(),
    uid: DS.attr('number'),
});

App.Router.map(function() {
    // put your routes here
});

App.IndexRoute = Ember.Route.extend({
    model: function() {
        return this.store.find('location');
    },
    actions: {
        addPodcast: function() {
            var locationView = this.container.lookup('view:newLocation');
            locationView.appendTo(App.rootElement);
        }
    }
});

App.ModalView = Ember.Mixin.create({
    templateName: "modal",
    modalTitle: "",
    saveButton: "Save Location",
    title: '',
    description: '',
    address: '',
    latitude: '0',
    longitude: '0',
    latlong: '0, 0',
    classNames: ["modal", "fade"],
    didInsertElement: function() {
        this.$().modal('show');
        this.$().on('hidden.bs.modal', this._viewDidHide);
        var editForm = this;

        var locationData = new google.maps.places.Autocomplete((document.getElementById('address')), { types: ['geocode'] });
        google.maps.event.addListener(locationData, 'place_changed', function() {
            var place = locationData.getPlace();
            editForm.set('address', place.formatted_address);
            editForm.set('latitude', place.geometry.location.d);
            editForm.set('longitude', place.geometry.location.e);
            editForm.set('latlong', place.geometry.location.e.toFixed(3)+', '+place.geometry.location.d.toFixed(3));
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

App.NewLocationView = Ember.View.extend(App.ModalView, {
    modalTitle: "Add a Location",
    saveButton: "Add Location",
    model: function() {
        return App.Location.createRecord();
    },
    actions: {
        submit: function() {
            var location = App.Location.store.createRecord('location');
            location.set('title', this.get('title'));
            location.set('address', this.get('address'));
            location.set('loc', [this.get('longitude'), this.get('latitude')]);
            location.set('uid', drupalSettings.user.uid);
            location.save();
            this.$(".close").click();
        }
    }
});

App.EditLocationView = Ember.View.extend(App.ModalView, {
    model: function() {
        return this.modelFor('location');
    }
})
