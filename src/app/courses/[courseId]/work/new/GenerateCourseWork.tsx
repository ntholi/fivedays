'use client';
import WandButton from '@/app/core/WandButton';
import { classroom_v1 } from 'googleapis';
import React from 'react';
import { notifications } from '@mantine/notifications';

type Props = {
  course: classroom_v1.Schema$Course;
  title: string;
  setDescription: React.Dispatch<React.SetStateAction<string>>;
};

export default function GenerateCourseWork({
  course,
  title,
  setDescription,
}: Props) {
  const [loading, setLoading] = React.useState(false);

  const handleSubmit = () => {
    setLoading(true);
    fetch('/api/ai/coursework', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        courseName: course.name as string,
        courseworkTitle: title,
      } as any),
    })
      .then((res) => res.json())
      .then((data) => {
        console.log(data);
        if (data.error) {
          notifications.show({
            title: 'Error',
            color: 'red',
            autoClose: 1000 * 30, // 30 seconds
            message: data.error,
          });
          return;
        }
        setDescription(data.description);
      })
      .finally(() => setLoading(false));
  };

  return (
    <WandButton disabled={!title} loading={loading} onClick={handleSubmit} />
  );
}
