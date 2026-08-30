import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import App from '../../Layouts/App.jsx';
import AeonConversation from '../../aeon/AeonConversation.jsx';
import AeonAura from '../../aeon/AeonAura.jsx';
import AeonCore from '../../aeon/AeonCore.jsx';
import { useAeon } from '../../aeon/useAeon.js';

export default function AeonConsole() {
  const { auth } = usePage().props;
  const aeon = useAeon();

  return (
    <App>
      <Head title="Aeon Copilot - DBEDC Guardian" />

      <div className="relative h-[calc(100vh-100px)] max-w-5xl mx-auto my-2 rounded-2xl overflow-hidden border border-[var(--gray-5,rgba(255,255,255,0.08))] bg-[var(--color-background,#090d16)] shadow-2xl flex flex-col">
        <AeonAura />

        {/* Header */}
        <header className="relative z-10 flex items-center justify-between px-6 py-4 border-b border-[var(--gray-4,rgba(255,255,255,0.08))] bg-[var(--gray-1,rgba(0,0,0,0.2))]">
          <div className="flex items-center gap-3">
            <AeonCore state={aeon.sending ? 'thinking' : 'listening'} size={40} />
            <div>
              <div className="flex items-center gap-2 font-bold text-base text-[var(--gray-12)]">
                <span>Aeon Copilot</span>
                <span className="px-2 py-0.5 rounded-full text-[10px] uppercase font-bold tracking-wider bg-[var(--accent-3,rgba(34,227,255,0.15))] text-[var(--accent-11,#22e3ff)] border border-[var(--accent-6)]">
                  Enterprise AI
                </span>
              </div>
              <span className="text-xs text-[var(--gray-10)]">
                Dhaka Bypass Expressway Operations & Quality Intelligence
              </span>
            </div>
          </div>
        </header>

        {/* Conversation Body */}
        <div className="relative z-10 flex-1 min-h-0">
          <AeonConversation
            messages={aeon.messages}
            sending={aeon.sending}
            stage={aeon.stage}
            usage={aeon.usage}
            onSend={aeon.send}
            onAction={(evt) => {
              if (evt?.block?.route) {
                window.location.href = evt.block.route;
              }
            }}
            onFeedback={aeon.feedback}
            user={auth?.user}
            hasAnimated={aeon.hasAnimated}
            markAnimated={aeon.markAnimated}
          />
        </div>
      </div>
    </App>
  );
}
