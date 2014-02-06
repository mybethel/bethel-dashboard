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
    }
});
