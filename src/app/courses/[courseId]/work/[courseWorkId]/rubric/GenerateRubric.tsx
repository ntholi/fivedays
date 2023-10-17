import WandButton from '@/app/core/WandButton';
import googleClassroom from '@/lib/config/googleClassroom';
import { notifications } from '@mantine/notifications';
import axios from 'axios';
import { classroom_v1 } from 'googleapis';
import { revalidatePath } from 'next/cache';
import React from 'react';

type Props = {
  courseId: string;
  courseWork: classroom_v1.Schema$CourseWork;
};

const getCourseName = async (courseId: string) => {
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({ id: courseId });
  return course.name;
};

export default async function GenerateRubric({ courseId, courseWork }: Props) {
  const courseName = await getCourseName(courseId);
  const handleSubmit = async () => {
    const { data } = await axios.post('/api/ai/rubric', {
      courseName: courseName,
      courseworkTitle: courseWork.title,
      courseWorkDescription: courseWork.description,
    });

    if (data.error) {
      notifications.show({
        title: 'Error',
        color: 'red',
        autoClose: 1000 * 30, // 30 seconds
        message: data.error,
      });
    }
    revalidatePath(`/courses/${courseId}/work/${courseWork.id}/rubric`);
  };
  return <WandButton />;
}
