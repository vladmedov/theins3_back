<template>
  <component
    :dusk="currentField.attribute"
    :is="currentField.fullWidth ? 'FullWidthField' : 'default-field'"
    :field="currentField"
    :errors="errors"
    :show-help-text="showHelpText"
    full-width-content
  >
    <template #field>
      <div ref="flexibleFieldContainer">
        <template
          v-for="(group, groupIndex) in orderedGroups"
          :key="group.key"
        >
          <form-nova-flexible-content-group
            :dusk="currentField.attribute + '-' + groupIndex"
            :field="currentField"
            :group="group"
            :index="groupIndex"
            :resource-name="resourceName"
            :resource-id="resourceId"
            :errors="errors"
            :mode="mode"
            @move-up="moveUp(group.key)"
            @move-down="moveDown(group.key)"
            @remove="remove(group.key)"
          />
          <div
            v-if="groupIndex === 0 && orderedGroups.length >= 2"
            class="flexible-menu-after-first"
          >
            <component
              :layouts="layouts"
              :is="currentField.menu.component"
              :field="currentField"
              :limit-counter="limitCounter"
              :limit-per-layout-counter="limitPerLayoutCounter"
              :errors="errors"
              :resource-name="resourceName"
              :resource-id="resourceId"
              @addGroup="insertAfterFirst($event)"
            />
          </div>
        </template>
      </div>

      <component
        :layouts="layouts"
        :is="currentField.menu.component"
        :field="currentField"
        :limit-counter="limitCounter"
        :limit-per-layout-counter="limitPerLayoutCounter"
        :errors="errors"
        :resource-name="resourceName"
        :resource-id="resourceId"
        @addGroup="appendGroup($event)"
      />
    </template>
  </component>
</template>

<script>
import FullWidthField from "nova-flexible-internal/components/FullWidthField";
import Sortable from "sortablejs";
import {
  DependentFormField,
  HandlesValidationErrors,
  mapProps,
} from "laravel-nova";
import Group from "nova-flexible-internal/group";

export default {
  mixins: [HandlesValidationErrors, DependentFormField],

  props: {
    ...mapProps(["resourceName", "resourceId", "mode"]),
  },

  components: { FullWidthField },

  computed: {
    layouts() {
      return this.currentField.layouts || false;
    },
    orderedGroups() {
      return this.order.reduce((groups, key) => {
        groups.push(this.groups[key]);
        return groups;
      }, []);
    },

    limitCounter() {
      if (
        this.currentField.limit === null ||
        typeof this.currentField.limit == "undefined"
      ) {
        return null;
      }

      return this.currentField.limit - Object.keys(this.groups).length;
    },

    limitPerLayoutCounter() {
      return this.layouts.reduce((layoutCounts, layout) => {
        if (layout.limit === null) {
          layoutCounts[layout.name] = null;

          return layoutCounts;
        }

        let count = Object.values(this.groups).filter(
          (group) => group.name === layout.name,
        ).length;

        layoutCounts[layout.name] = layout.limit - count;

        return layoutCounts;
      }, {});
    },
  },

  data() {
    return {
      order: [],
      groups: {},
      files: {},
      sortableInstance: null,
    };
  },

  beforeUnmount() {
    if (this.sortableInstance) {
      this.sortableInstance.destroy();
    }
  },

  methods: {
    setInitialValue() {
      this.value = this.currentField.value || [];
      this.files = {};

      this.populateGroups();
      this.$nextTick(this.initSortable.bind(this));
    },

    fill(formData) {
      let key, group;

      this.value = [];
      this.files = {};

      for (var i = 0; i < this.order.length; i++) {
        key = this.order[i];
        group = this.groups[key].serialize();

        this.value.push({
          layout: group.layout,
          key: group.key,
          attributes: group.attributes,
        });

        this.files = { ...this.files, ...group.files };
      }

      this.appendFieldAttribute(formData, this.currentField.attribute);
      formData.append(
        this.currentField.attribute,
        this.value.length ? JSON.stringify(this.value) : "",
      );

      for (let file in this.files) {
        formData.append(file, this.files[file]);
      }

      this.$nextTick(this.initSortable.bind(this));
    },

    appendFieldAttribute(formData, attribute) {
      let registered = [];

      if (formData.has("___nova_flexible_content_fields")) {
        registered = JSON.parse(
          formData.get("___nova_flexible_content_fields"),
        );
      }

      registered.push(attribute);

      formData.set(
        "___nova_flexible_content_fields",
        JSON.stringify(registered),
      );
    },

    handleChange(value) {
      this.value = value || [];
      this.files = {};

      this.populateGroups();
    },

    populateGroups() {
      this.order.splice(0, this.order.length);
      this.groups = {};

      for (var i = 0; i < this.value.length; i++) {
        this.addGroup(
          this.getLayout(this.value[i].layout),
          this.value[i].attributes,
          this.value[i].key,
          this.currentField.collapsed,
        );
      }
    },

    getLayout(name) {
      if (!this.layouts) return;
      return this.layouts.find((layout) => layout.name == name);
    },

    appendGroup(layout) {
      this.addGroup(layout);
    },

    insertAfterFirst(layout) {
      this.addGroup(layout, undefined, undefined, undefined, 1);
    },

    addGroup(layout, attributes, key, collapsed, insertIndex) {
      if (!layout) return;

      collapsed = collapsed || false;

      let fields = attributes || JSON.parse(JSON.stringify(layout.fields)),
        group = new Group(
          layout.name,
          layout.title,
          fields,
          this.currentField,
          key,
          collapsed,
        );

      this.groups[group.key] = group;

      if (typeof insertIndex === "number") {
        const idx = Math.min(Math.max(insertIndex, 0), this.order.length);
        this.order.splice(idx, 0, group.key);
      } else {
        this.order.push(group.key);
      }
    },

    moveUp(key) {
      let index = this.order.indexOf(key);

      if (index <= 0) return;

      this.order.splice(index - 1, 0, this.order.splice(index, 1)[0]);
    },

    moveDown(key) {
      let index = this.order.indexOf(key);

      if (index < 0 || index >= this.order.length - 1) return;

      this.order.splice(index + 1, 0, this.order.splice(index, 1)[0]);
    },

    remove(key) {
      let index = this.order.indexOf(key);

      if (index < 0) return;

      this.order.splice(index, 1);
      delete this.groups[key];
    },

    syncOrderFromDom() {
      const container = this.$refs["flexibleFieldContainer"];
      if (!container) {
        return;
      }

      const keys = Array.from(container.children)
        .filter(
          (el) =>
            el.classList && !el.classList.contains("flexible-menu-after-first"),
        )
        .map((el) => el.id)
        .filter(Boolean);

      if (keys.length !== this.order.length) {
        return;
      }

      this.order.splice(0, this.order.length, ...keys);
    },

    initSortable() {
      const containerRef = this.$refs["flexibleFieldContainer"];

      if (!containerRef || this.sortableInstance) {
        return;
      }

      this.sortableInstance = Sortable.create(containerRef, {
        ghostClass: "nova-flexible-content-sortable-ghost",
        dragClass: "nova-flexible-content-sortable-drag",
        chosenClass: "nova-flexible-content-sortable-chosen",
        direction: "vertical",
        handle: ".nova-flexible-content-drag-button",
        filter: ".flexible-menu-after-first",
        preventOnFilter: false,
        scrollSpeed: 5,
        animation: 500,
        onEnd: () => {
          this.syncOrderFromDom();
        },
      });
    },
  },
};
</script>

<style scoped>
.flexible-menu-after-first {
  margin-bottom: 1rem;
}
</style>
