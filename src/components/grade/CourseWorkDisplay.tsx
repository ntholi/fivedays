import { Tabs } from '@mantine/core';
import { Prism } from '@mantine/prism';
import axios from 'axios';
import React, { useEffect, useState } from 'react';

type Props = {
  submission: StudentSubmission | null;
};

export default function CourseWorkDisplay({ submission }: Props) {
  const first = submission?.attachments?.[0];
  const [activeTab, setActiveTab] = useState<string | null>(first?.id!);
  const [content, setContent] = useState<string>('Hello World');

  useEffect(() => {
    async function getData() {
      const response = await axios.get('/api/filereader', {
        params: { fileId: submission?.attachments?.at(0)?.id },
      });
      setContent(response.data);
    }
    getData();
  }, [submission]);

  return (
    <>
      <Tabs value={activeTab} onTabChange={setActiveTab}>
        <Tabs.List>
          {submission?.attachments?.map((it) => (
            <Tabs.Tab value={it.id!}>{it.title}</Tabs.Tab>
          ))}
        </Tabs.List>
        {submission?.attachments?.map((it) => (
          <Tabs.Panel value={it.id!}>
            <Prism withLineNumbers language="tsx">
              {content}
            </Prism>
          </Tabs.Panel>
        ))}
      </Tabs>
    </>
  );
}
