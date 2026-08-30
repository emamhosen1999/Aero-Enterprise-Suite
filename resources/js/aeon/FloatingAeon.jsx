import React, { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import FloatingAeonButton from './FloatingAeonButton.jsx';
import AeonDrawer from './AeonDrawer.jsx';
import { useAeon } from './useAeon.js';

function readAuthUser() {
  try {
    const el = document.querySelector('[data-page]');
    if (!el) return null;
    return JSON.parse(el.dataset.page)?.props?.auth?.user ?? null;
  } catch {
    return null;
  }
}

function readAeonAvailable(page) {
  if (page && page.props) return page.props?.aeon?.available === true;
  try {
    const el = document.querySelector('[data-page]');
    if (!el) return false;
    return JSON.parse(el.dataset.page)?.props?.aeon?.available === true;
  } catch {
    return false;
  }
}

export default function FloatingAeon() {
  const [user, setUser] = useState(readAuthUser);
  const [available, setAvailable] = useState(readAeonAvailable);
  const aeon = useAeon();

  useEffect(() => {
    return router.on('navigate', (event) => {
      const page = event?.detail?.page;
      setUser(page?.props?.auth?.user ?? null);
      setAvailable(readAeonAvailable(page));
    });
  }, []);

  const onAction = useCallback((evt) => {
    const route = evt?.block?.route;
    if (route && (evt.kind === 'confirm' || evt.kind === 'navigate')) {
      aeon.close();
      router.visit(route);
    }
  }, [aeon]);

  if (!user || !available) return null;

  return (
    <>
      <FloatingAeonButton onClick={aeon.open} />
      <AeonDrawer
        isOpen={aeon.isOpen}
        onClose={aeon.close}
        messages={aeon.messages}
        sending={aeon.sending}
        stage={aeon.stage}
        usage={aeon.usage}
        onSend={aeon.send}
        user={user}
        onAction={onAction}
        onFeedback={aeon.feedback}
        hasAnimated={aeon.hasAnimated}
        markAnimated={aeon.markAnimated}
      />
    </>
  );
}
