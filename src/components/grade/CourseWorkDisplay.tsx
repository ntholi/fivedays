import { Tabs } from '@mantine/core';
import axios from 'axios';
import React, { useEffect, useState } from 'react';

type Props = {
  submission: StudentSubmission | null;
};

export default function CourseWorkDisplay({ submission }: Props) {
  const first = submission?.attachments?.[0];
  const [activeTab, setActiveTab] = useState<string | null>(first?.id!);

  useEffect(() => {
    async function getData() {
      const response = await axios.get('/api/filereader', {
        params: { fileId: submission?.attachments?.at(0)?.id },
      });
      console.log(response);
    }
    getData();
  }, []);

  return (
    <>
      <Tabs value={activeTab} onTabChange={setActiveTab}>
        <Tabs.List>
          {submission?.attachments?.map((it) => (
            <Tabs.Tab value={it.id!}>{it.title}</Tabs.Tab>
          ))}
        </Tabs.List>
        {submission?.attachments?.map((it) => (
          <Tabs.Panel value={it.id!}>{it.title}</Tabs.Panel>
        ))}
      </Tabs>
    </>
  );
}
