/** Shared open/closed state for the Ask Itqan chat panel. */
const askItqanState = {
  open: false,
  toggle() {
    this.open = !this.open;
    m.redraw();
  },
  show() {
    this.open = true;
    m.redraw();
  },
  hide() {
    this.open = false;
    m.redraw();
  },
};

export default askItqanState;
