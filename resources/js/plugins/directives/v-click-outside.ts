const HANDLERS_PROPERTY = "__v-click-outside";
const EVENTS = ["click"];

const processDirectiveArguments = (bindingValue) => {
  const isFunction = typeof bindingValue === "function";
  if (!isFunction && typeof bindingValue !== "object") {
    throw new Error("v-click-outside: Binding value must be a function or an object");
  }
  return {
    handler: isFunction ? bindingValue : bindingValue.handler,
    events: bindingValue.events || EVENTS,
    isActive: bindingValue.isActive !== false,
  };
};

const onEvent = (el, event, handler) => {
  if (!el.contains(event.target)) {
    handler(event);
  }
};

const beforeMount = (el, { value }) => {
  const { events, handler, isActive } = processDirectiveArguments(value);
  if (!isActive) return;

  el[HANDLERS_PROPERTY] = events.map(eventName => {
    const handlerWrapper = event => onEvent(el, event, handler);
    document.documentElement.addEventListener(eventName, handlerWrapper);
    return { event: eventName, handlerWrapper };
  });
};

const unmounted = (el) => {
  const handlers = el[HANDLERS_PROPERTY] || [];
  handlers.forEach(({ event, handlerWrapper }) => {
    document.documentElement.removeEventListener(event, handlerWrapper);
  });
  delete el[HANDLERS_PROPERTY];
};

const updated = (el, { value, oldValue }) => {
  if (value === oldValue) return;
  unmounted(el);
  beforeMount(el, { value });
};

export default {
  install(Vue) {
    Vue.directive("click-outside", { beforeMount, updated, unmounted });
  },
};
