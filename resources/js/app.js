// The marketing site ships no JavaScript, deliberately.
//
// Everything interactive is native HTML/CSS: the mobile menu and the FAQ are
// <details>/<summary>, the language switch is a plain link, and the pricing page
// shows every price at once rather than hiding half of it behind a toggle. That
// keeps the copy indexable, the page usable before (and without) any script, and
// the largest-contentful-paint free of blocking work.
//
// This entry stays registered in vite.config.js so adding a script later is a
// one-file change and the manifest shape doesn't shift underneath the layout.
