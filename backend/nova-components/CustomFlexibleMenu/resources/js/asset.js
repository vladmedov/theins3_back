//import CustomFlexibleMenu from './components/CustomFlexibleMenu'

Nova.booting(app => {
  //app.component('custom-flexible-menu', CustomFlexibleMenu)
  app.component(
    "custom-flexible-menu",
    require("./components/CustomFlexibleMenu.vue").default,
  );
})
