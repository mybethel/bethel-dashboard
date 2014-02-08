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
            App.ModalView.create({ title: "Add a Location", content: "My content" }).append();
        }
    }
});

App.ModalView = Ember.View.extend({
    templateName: "modal",
    title: "",
    content: "",
    classNames: ["modal", "fade"],
    didInsertElement: function() {
        this.$().modal('show');
        this.$().one("hidden", this._viewDidHide);
    },
    // modal dismissed by example clicked in X, make sure the modal view is destroyed
    _viewDidHide: function() {
        if (!this.isDestroyed) {
            this.destroy();
        }
    },
    // here we click in close button so _viewDidHide is called
    close: function() {        
        this.$(".close").click();
    }
});
